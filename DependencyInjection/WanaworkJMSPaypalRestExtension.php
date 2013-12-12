<?php
namespace Wanawork\JMS\PaypalRestBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class WanaworkJMSPaypalRestExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');
        
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        
        if (isset($config['paypal']['http']['retry'])) {
            $container->setParameter('wanawork_jms_paypal_rest.paypal.http.retry', $config['paypal']['http']['retry']);    
        }

        if (isset($config['paypal']['http']['connection_timeout'])) {
            $container->setParameter('wanawork_jms_paypal_rest.paypal.http.connection_timeout', $config['paypal']['http']['connection_timeout']);
        }
        
        if (isset($config['paypal']['log']['log_enabled'])) {
            $container->setParameter('wanawork_jms_paypal_rest.paypal.log.log_enabled', $config['paypal']['log']['log_enabled']);
        }
        
        if (isset($config['paypal']['log']['file_name'])) {
            $container->setParameter('wanawork_jms_paypal_rest.paypal.log.file_name', $config['paypal']['log']['file_name']);
        }
        
        if (isset($config['paypal']['log']['log_level'])) {
            $container->setParameter('wanawork_jms_paypal_rest.paypal.log.log_level', $config['paypal']['log']['log_level']);
        }
        
        if (isset($config['paypal']['service']['mode'])) {
            $container->setParameter('wanawork_jms_paypal_rest.paypal.service.mode', $config['paypal']['service']['mode']);
        }
        
        if (isset($config['secret'])) {
            $container->setParameter('wanawork_jms_paypal_rest.secret', $config['secret']);
        }
        
        if (isset($config['client_id'])) {
            $container->setParameter('wanawork_jms_paypal_rest.client_id', $config['client_id']);
        }
        
        if (isset($config['success_url'])) {
            $container->setParameter('wanawork_jms_paypal_rest.success_url', $config['success_url']);
        }
        
        if (isset($config['cancel_url'])) {
            $container->setParameter('wanawork_jms_paypal_rest.cancel_url', $config['cancel_url']);
        }
    }
}
