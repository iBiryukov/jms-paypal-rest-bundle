<?php
namespace Wanawork\JMS\PaypalRestBundle\Plugin;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Plugin\GatewayPlugin;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\Exception\InternalErrorException;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;

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
use PayPal\Api\PaymentExecution;

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
        if ($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            $this->createPayment($transaction);
        } elseif($transaction->getState() === FinancialTransactionInterface::STATE_PENDING) {
            $this->approvePayment($transaction);
        } else {
            throw new \JMS\Payment\CoreBundle\Plugin\Exception\InvalidDataException(
                sprintf("Transaction canno be approved because its state is %s", $transaction->getState())
            );
        }
    }
    
    private function approvePayment(FinancialTransactionInterface $transaction)
    {
        $pendingTransaction = $transaction->getPayment()->getPendingTransaction();
        if ($pendingTransaction === null || !$pendingTransaction->getTrackingId()) {
            throw new InternalErrorException("Cannot approve payment with no pending approve transcation");
        }                
        
        try {
            $apiContext = $this->createContext();
            $paymentId = $pendingTransaction->getTrackingId();
            $payment = Payment::get($paymentId, $apiContext);
            
            if ($payment->getState() !== 'created') {
                throw new FinancialException(
                    sprintf('Cannot approve payment. Expected payment state "created", got: "%s"', $payment->getState())
                );
            }
             
            $transaction->setTrackingId($payment->getId());
            $transaction->setTransactionType('payment-id');
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
            $extendedData->set('state', $payment->getState());
            $extendedData->set('create_time', $payment->getCreateTime());
            $extendedData->set('update_time', $payment->getUpdateTime());
            $extendedData->set('links', $payment->getLinks()->toArray());
        } catch (\PayPal\Exception\PPConnectionException $e) {
            if ($payment->__isset('state')) {
                $transaction->setResponseCode($payment->getState());
            }
            
            $newEx = new FinancialException(
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
        
    }
    
    private function createPayment(FinancialTransactionInterface $transaction)
    {
        $extendedData = $transaction->getExtendedData();
        $paymentInstruction = $transaction->getPayment()->getPaymentInstruction();
        
        $payer = new Payer();
        $payer->setPayment_method("paypal");
        
        $amount = new Amount();
        $amount->setCurrency($paymentInstruction->getCurrency());
        $amount->setTotal($transaction->getRequestedAmount());
        
        $paypalTran = new Transaction();
        $paypalTran->setAmount($amount);
        
        $successUrl = $this->successUrl;
        $successUrl .= (strpos($successUrl, '?') === false) ?
        "?payment={$transaction->getPayment()->getId()}" : "&payment={$transaction->getPayment()->getId()}";
        
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturn_url($successUrl);
        $redirectUrls->setCancel_url($this->cancelUrl);
        
        $payment = new Payment();
        $payment->setIntent("sale");
        $payment->setPayer($payer);
        $payment->setRedirect_urls($redirectUrls);
        $payment->setTransactions(array($paypalTran));
        
        try {
            $apiContext = $this->createContext();
            $payment->create($apiContext);
            
            if ($payment->getState() !== 'created') {
                throw new FinancialException();
            }
            
            $transaction->setTrackingId($payment->getId());
            $transaction->setTransactionType('payment-id');
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
            $extendedData->set('state', $payment->getState());
            $extendedData->set('create_time', $payment->getCreateTime());
            $extendedData->set('update_time', $payment->getUpdateTime());
            
            foreach($payment->getLinks() as $link) {
                if($link->getRel() == 'approval_url') {
                    $extendedData->set('approval_url', $link->getHref());
                }
            }
        } catch (\PayPal\Exception\PPConnectionException $ex) {
            if ($payment->__isset('state')) {
                $transaction->setResponseCode($payment->getState());
            }
        
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
        
        $actionRequest = new \JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException(
            'User has not yet authorized the transaction.'
        );
        $actionRequest->setFinancialTransaction($transaction);
        $actionRequest->setAction(new VisitUrl($extendedData->get('approval_url')));
        
        throw $actionRequest;
        
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
        try {
            // Get the payment Object by passing paymentId
            // payment id was previously stored in session in
            $paymentInstruction = $transaction->getPayment()->getPaymentInstruction();
            $approveTransaction = $transaction->getPayment()->getApproveTransaction();
            if ($approveTransaction === null) {
                throw new \Exception(
                    'Payment is missing "approve" transaction'
                );
            }
            
            $apiContext = $this->createContext();
            $paymentId = $approveTransaction->getTrackingId();
            $payment = Payment::get($paymentId, $apiContext);
            
            if ($payment->getState() !== 'approved') {
                $e = new FinancialException(
        	       sprintf("Payment was not approved. Status: '%s'", $payment->getState())
                );
                $e->setFinancialTransaction($transaction);
                $transaction->getReasonCode('Payment status: ' . (strlen($payment->getState())) ? $payment->getState() : 'Failed');
                throw $e;
            }
            
            $execution = new PaymentExecution();
            $execution->setPayer_id($payment->getPayer()->getPayerInfo()->getPayerId());
            $completedPayment = $payment->execute($execution, $apiContext);
            
            $transaction->setTrackingId($completedPayment->getId());
            $transaction->setTransactionType('payment-id');
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
            $extendedData->set('state', $completedPayment->getState());
            $extendedData->set('create_time', $completedPayment->getCreateTime());
            $extendedData->set('update_time', $completedPayment->getUpdateTime());
        	
        } catch (\PayPal\Exception\PPConnectionException $ex) {
            
            $newEx = new FinancialException(
                "Could not get approval for payment",
                null,
                $ex
            );
            
            $extendedData->set('paypal_response', is_array($ex->getData()) ?
                json_encode($ex->getData()) : $ex->getData());
            $transaction->setResponseCode('Failed');
            $transaction->setReasonCode('DepositActionFailed');
            $newEx->setFinancialTransaction($transaction);
            throw $newEx;
        }
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
    
    /**
     * Get info about the given payment from paypal
     * @param string $paymentId
     * @return \PayPal\Api\Payment
     */
    public function getPayment($paymentId)
    {
        $apiContext = $this->createContext();
        return Payment::get($paymentId, $apiContext);
    }
}