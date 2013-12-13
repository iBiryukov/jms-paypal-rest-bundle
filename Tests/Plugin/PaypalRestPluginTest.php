<?php
namespace Wanawork\JMS\PaypalRestBundle\Tests\Plugin;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wanawork\JMS\PaypalRestBundle\DependencyInjection\WanaworkJMSPaypalRestExtension;
use Wanawork\JMS\PaypalRestBundle\Plugin\PaypalRestPlugin;
use JMS\Payment\CoreBundle\JMSPaymentCoreBundle;
use JMS\Payment\CoreBundle\DependencyInjection\JMSPaymentCoreExtension;
use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use JMS\Payment\CoreBundle\Entity\Payment;
use JMS\Payment\CoreBundle\Entity\FinancialTransaction;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
class PaypalRestPluginTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
    private $container;
    
    private $extension;
    
    private $ppc;
    
    private $config;
    
    protected function setUp()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.logs_dir', sys_get_temp_dir());
        $container->setParameter('kernel.bundles', array(
            'WanaworkJMSPaypalRestBundle' => 'Wanawork\JMS\PaypalRestBundle'
        ));
        
        $this->config = $config = array(
            'client_id' => 'EBWKjlELKMYqRNQ6sYvFo64FtaRLRR5BdHEESmha49TM',
            'secret' => 'EO422dn3gQLgDbuwqTjzrFgFtaRLRR5BdHEESmha49TM',
            'cancel_url' => 'http://wannawork.ie/cancel',
            'success_url' => 'http://wannawork.ie/success',
            'paypal' => array(
        	   'log' => array(
            	   'file_name' => '/tmp/paypal.log'
                )
            )
        );
        
        $this->extension = new WanaworkJMSPaypalRestExtension();
        $this->extension->load(array($config), $container);
        $container->compile();
        
        $this->container = $container;
    }
    
    public function testApproveTransaction()
    {
        // Create Trasaction
        $paypalRest = $this->container->get('wanawork_jms_paypal_rest.example.class');
        $this->assertTrue($paypalRest instanceof PaypalRestPlugin);
        $apiOptions = $paypalRest->getPaypalApiOptions();
        $this->assertArrayHasKey('service.mode', $apiOptions);
        $this->assertTrue($paypalRest->isDebug());
        
        $paymentInstruction = new PaymentInstruction(
            $amount = 5, $currency = 'EUR', $paymentSystemName = 'paypal_rest'
        );
        
        $payment = new Payment($paymentInstruction, $paymentInstruction->getAmount());
        $transaction = new FinancialTransaction();
        $payment->addTransaction($transaction);
        $transaction->setRequestedAmount($paymentInstruction->getAmount());
        
        try {
            $paypalRest->approve($transaction, $retry = false);
            $this->fail('Expected \JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException exception');
        } catch (\JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException $e) {
            $this->assertTrue($e->getAction() instanceof \JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl);
        } catch (\Exception $ex) {
            $this->fail('Expected \JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException exception');
        }
        
        $this->assertNotNull($transaction->getTrackingId());
        $this->assertNotNull($transaction->getTransactionType());
        $this->assertSame(PluginInterface::RESPONSE_CODE_SUCCESS, $transaction->getResponseCode());
        $this->assertSame(PluginInterface::REASON_CODE_SUCCESS, $transaction->getReasonCode());
        $this->assertTrue($transaction->getExtendedData()->has('approval_url'));
        $this->assertTrue(is_string($transaction->getExtendedData()->get('approval_url')));
        
        $payment = $paypalRest->getPayment($transaction->getTrackingId());
        $this->assertNotNull($payment);
        $this->assertSame('created', $payment->getState());
        $this->assertSame('created', $transaction->getExtendedData()->get('state'));
        
        // Approvate Transaction
//         $client = new \Goutte\Client();
//         $client->followRedirects();
        
//         $requestOptions = array(
//         	'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36',
//             'Connection' => 'keep-alive',
//             'Cache-Control' => 'max-age=0',
//             'Accept-Language' => 'en-US,en;q=0.8,es;q=0.6,ru;q=0.4',
//             'Accept-Encoding' => 'gzip,deflate,sdch',
//             'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            
//         );
//         $crawler = $client->request('GET', $e->getAction()->getUrl(), $parameters = array(), $files = array(), $server = $requestOptions);
//         $this->assertSame(200, $client->getResponse()->getStatus());
//         $form = $crawler->filter('#submitLogin')->form();
//         $crawler = $client->submit($form, array(
//         	   'login_email' => 'p.personal@wannawork.ie',
//             'login_password' => ''
//         ));
        
//         $form = $crawler->filter('#continue_abovefold')->form();
//         $client->submit($form);
        
    }
    
}