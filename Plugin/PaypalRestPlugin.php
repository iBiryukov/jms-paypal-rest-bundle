<?php
namespace Wanawork\JMS\PaypalRestBundle\Plugin;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Plugin\GatewayPlugin;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Address;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\FundingInstrument;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\ItemList;
use PayPal\Api\Item;

/*
 * Copyright 2013 Ilya Biryukov <ilya@wannawork.ie>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PaypalRestPlugin extends GatewayPlugin
{
    /**
     * URL To return to after successful transaction
     * @var string
     */
    private $successUrl;
    
    /**
     * URL to return after unsuccessful transaction
     * @var string
     */
    private $cancelUrl;
    
    private $clientId;
    
    private $secret;
    
    private $paypalApiOptions;
    
    public function __construct($successUrl, $cancelUrl, $clientId, $secret, array $paypalApiOptions)
    {
        if (!in_array($paypalApiOptions['service.mode'], array('sandbox', 'live'), true)) {
            throw new \InvalidArgumentException(
        	    sprintf(
        	        "Invalid Paypal option 'service.mode': %s. Allowed options: 'sandbox' or 'live'", 
        	        $paypalApiOptions['service.mode']
                )
            );    
        }
        $isDebug = $paypalApiOptions['service.mode'] === 'sandbox';
        $this->successUrl = $successUrl;
        $this->cancelUrl = $cancelUrl;
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->paypalApiOptions = $paypalApiOptions;
        parent::__construct($isDebug);
    }
    
    
    
    /**
     * Create API Context getting paypal token
     * @return \PayPal\Rest\ApiContext
     */
    private function createContext()
    {
        $clientId = $this->clientId;
        $secret = $this->secret;
        $paypalApiOptions = $this->paypalApiOptions;
        
        $apiContext = new ApiContext(new OAuthTokenCredential($clientId, $secret));
        $apiContext->setConfig(array(
            'mode' => $paypalApiOptions['service.mode'],
            'http.ConnectionTimeOut' => $paypalApiOptions['http.connection_timeout'],
            'log.LogEnabled' => $paypalApiOptions['log.log_enabled'],
            'log.FileName' => $paypalApiOptions['log.file_name'],
            'log.LogLevel' => $paypalApiOptions['log.log_level']
        ));
        return $apiContext;
    }
    
    /**
     * This method executes an approve transaction.
     *
     * By an approval, funds are reserved but no actual money is transferred. A
     * subsequent deposit transaction must be performed to actually transfer the
     * money.
     *
     * A typical use case, would be Credit Card payments where funds are first
     * authorized.
     *
     * @param FinancialTransactionInterface $transaction
     * @param boolean $retry Whether this is a retry transaction
     * @return void
     */
    function approve(FinancialTransactionInterface $transaction, $retry)
    {
        $extendedData = $transaction->getExtendedData();
        $paymentInstruction = $transaction->getPayment()->getPaymentInstruction();
        // ### Payer
        // A resource representing a Payer that funds a payment
        // Use the List of `FundingInstrument` and the Payment Method
        // as 'credit_card'
        $payer = new Payer();
        $payer->setPayment_method("paypal");
        
        // ### Amount
        // Let's you specify a payment amount.
        $amount = new Amount();
        $amount->setCurrency("USD");
        $amount->setTotal($paymentInstruction->getAmount());
        
        // ### Transaction
        // A transaction defines the contract of a
        // payment - what is the payment for and who
        // is fulfilling it. Transaction is created with
        // a `Payee` and `Amount` types
        $paypalTran = new Transaction();
        $paypalTran->setAmount($amount);
        $paypalTran->setDescription("This is the payment description.");
        
        // ### Redirect urls
        // Set the urls that the buyer must be redirected to after 
        // payment approval/ cancellation.
        $baseUrl = 'http://wannawork.ie/';
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturn_url("$baseUrl/ExecutePayment.php?success=true");
        $redirectUrls->setCancel_url("$baseUrl/ExecutePayment.php?success=false");
        
        // ### Payment
        // A Payment Resource; create one using
        // the above types and intent as 'sale'
        $payment = new Payment();
        $payment->setIntent("sale");
        $payment->setPayer($payer);
        $payment->setRedirect_urls($redirectUrls);
        $payment->setTransactions(array($paypalTran));
        
        // ### Create Payment
        // Create a payment by posting to the APIService
        // using a valid apiContext.
        // (See bootstrap.php for more on `ApiContext`)
        // The return object contains the status and the
        // url to which the buyer must be redirected to
        // for payment approval
        try {
            $apiContext = $this->createContext();
            $payment->create($apiContext);
            
        } catch (\PayPal\Exception\PPConnectionException $ex) {
            $newEx = new \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException(
        	    "Could not get approval for payment",
                null,
                $ex
            );
            
            $extendedData->set('paypal_response', is_array($ex->getData()) ? 
                json_encode($ex->getData()) : $ex->getData());
            $transaction->setResponseCode('Failed');
            $transaction->setReasonCode('PaymentActionFailed');
            $newEx->setFinancialTransaction($transaction);
            throw $newEx;
        }
        
        // ### Redirect buyer to paypal
        // Retrieve buyer approval url from the `payment` object.
        foreach($payment->getLinks() as $link) {
        	if($link->getRel() == 'approval_url') {
        		$redirectUrl = $link->getHref();
        	}
        }
        
    }
    
    /**
     * This method executes a deposit transaction (aka capture transaction).
     *
     * This method requires that the Payment has already been approved in
     * a prior transaction.
     *
     * A typical use case are Credit Card payments.
     *
     * @param FinancialTransactionInterface $transaction
     * @param boolean $retry
     * @return void
    */
    function deposit(FinancialTransactionInterface $transaction, $retry)
    {
        
    }
    
    /**
     * Whether this plugin can process payments for the given payment system.
     *
     * A plugin may support multiple payment systems. In these cases, the requested
     * payment system for a specific transaction  can be determined by looking at
     * the PaymentInstruction which will always be accessible either directly, or
     * indirectly.
     *
     * @param string $paymentSystemName
     * @return boolean
    */
    function processes($paymentSystemName)
    {
        return $paymentSystemName === 'paypal_rest';
    }

    public function getSuccessUrl()
    {
        return $this->successUrl;
    }

    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    public function getPaypalApiOptions()
    {
        return $this->paypalApiOptions;
    }
}