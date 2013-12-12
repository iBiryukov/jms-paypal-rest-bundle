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
class PaypalRestPluginTest  extends \PHPUnit_Framework_TestCase
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
        
        $paypalRest->approve($transaction, $retry = false);
        
    }
    
}