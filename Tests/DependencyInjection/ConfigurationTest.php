<?php
namespace Wanawork\JMS\PaypalRestBundle\Tests\DependencyInjection;

use Wanawork\JMS\PaypalRestBundle\DependencyInjection\WanaworkJMSPaypalRestExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    private $extension;
    
    public function testConfigLoad()
    {
        $config = array('paypal' => array(
            	'http' => array(
            	    'retry' => 2,
            	    'connection_timeout' => 2,
                ),
                'log' => array(
            		'log_enabled' => false,
                    'file_name' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . "paypal_".time().".log",
                    'log_level' => 'ERROR',
            	),
                'service' => array(
                	'mode' => 'live',
                ),
            ),
            'client_id' => 'test',
            'secret' => 'test',
        );
        
        
        $this->extension->load(array($config), $container = $this->getContainer());
        $container->compile();
        
        $parameterBag = $container->getParameterBag();
        $this->assertTrue($parameterBag->has('wanawork_jms_paypal_rest.paypal.http.retry')); 
        $this->assertSame($config['paypal']['http']['retry'], $parameterBag->get('wanawork_jms_paypal_rest.paypal.http.retry'));       

        $this->assertTrue($parameterBag->has('wanawork_jms_paypal_rest.paypal.http.connection_timeout'));
        $this->assertSame(
            $config['paypal']['http']['connection_timeout'], 
            $parameterBag->get('wanawork_jms_paypal_rest.paypal.http.connection_timeout')
        );
    
        $this->assertTrue($parameterBag->has('wanawork_jms_paypal_rest.paypal.log.log_enabled'));
        $this->assertSame(
            $config['paypal']['log']['log_enabled'], 
            $parameterBag->get('wanawork_jms_paypal_rest.paypal.log.log_enabled')
        );
        
        $this->assertTrue($parameterBag->has('wanawork_jms_paypal_rest.paypal.log.file_name'));
        $this->assertSame(
            $config['paypal']['log']['file_name'],
            $parameterBag->get('wanawork_jms_paypal_rest.paypal.log.file_name')
        );
        
        $this->assertTrue($parameterBag->has('wanawork_jms_paypal_rest.paypal.log.log_level'));
        $this->assertSame(
            $config['paypal']['log']['log_level'],
            $parameterBag->get('wanawork_jms_paypal_rest.paypal.log.log_level')
        );
        
        $this->assertTrue($parameterBag->has('wanawork_jms_paypal_rest.paypal.service.mode'));
        $this->assertSame(
            $config['paypal']['service']['mode'],
            $parameterBag->get('wanawork_jms_paypal_rest.paypal.service.mode')
        );
        
        $this->assertTrue($parameterBag->has('wanawork_jms_paypal_rest.client_id'));
        $this->assertSame(
            $config['client_id'],
            $parameterBag->get('wanawork_jms_paypal_rest.client_id')
        );
        
        $this->assertTrue($parameterBag->has('wanawork_jms_paypal_rest.secret'));
        $this->assertSame(
            $config['secret'],
            $parameterBag->get('wanawork_jms_paypal_rest.secret')
        );
    }
    
    public function testPluginIsRegisteredWithJMSCore()
    {
        $this->extension->load($this->getDefaultConfig(), $container = $this->getContainer());
        $this->assertTrue($container->getDefinition('payment.plugin.paypal_rest')->hasTag('payment.plugin'));
    }
    
    public function testClientIdIsRequired()
    {
        $config = array(
        	array('secret' => 'abc')
        );
        
        try {
            $this->extension->load($config, $container = $this->getContainer());
            $this->fail('Expected an expection');
        } catch (\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException $e) {
            $message = 'The child node "client_id" at path "wanawork_jms_paypal_rest" must be configured.';
            $this->assertSame($message, $e->getMessage());
        }
        
    }
    
    public function testSecretIsRequired()
    {
        $config = array(
            array('client_id' => 'abc')
        );
    
        try {
            $this->extension->load($config, $container = $this->getContainer());
            $this->fail('Expected an expection');
        } catch (\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException $e) {
            $message = 'The child node "secret" at path "wanawork_jms_paypal_rest" must be configured.';
            $this->assertSame($message, $e->getMessage());
        }
    
    }
    
    protected function setUp()
    {
        $this->extension = new WanaworkJMSPaypalRestExtension();    
    }
    
    private function getContainer()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.bundles', array('WanaworkJMSPaypalRestBundle' => 'Wanawork\JMS\PaypalRestBundle'));
    
        return $container;
    }
    
    private function getDefaultConfig()
    {
        return array(
        	array(
        	    'secret' => 'test',
        	    'client_id' => 'test'
            )
        );
    }
}