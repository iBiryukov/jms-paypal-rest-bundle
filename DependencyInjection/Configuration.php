<?php

namespace Wanawork\JMS\PaypalRestBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('wanawork_jms_paypal_rest');
        $rootNode
            ->children()
            
                ->variableNode('secret')->isRequired()->cannotBeEmpty()->end()
                ->variableNode('client_id')->isRequired()->cannotBeEmpty()->end()
                        
                ->arrayNode('paypal')
                    ->children()
                        ->arrayNode('service')
                            ->children()
                                ->enumNode('mode')->cannotBeEmpty()->values(array('sandbox', 'live'))->end()
                            ->end()
                        ->end()
                        ->arrayNode('log')
                            ->children()
                                ->booleanNode('log_enabled')->end()
                                ->variableNode('file_name')->cannotBeEmpty()
                                    ->validate()->ifTrue(function($v){
                                    	if(!file_exists($v)) {
                                    	    $fp = fopen($v, 'w');
                                    	    if($fp) {
                                    	        fclose($fp);
                                    	    }
                                    	}
                                    	return !is_writable($v);
                                    })->thenInvalid('Log File "%s" is not writable')->end()
                                ->end()
                                ->enumNode('log_level')
                                    ->cannotBeEmpty()->values(array(
                                    	'FINE', 'INFO', 'WARN', 'ERROR',
                                    ))
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('http')
                            ->children()
                                ->integerNode('retry')->min(0)->cannotBeEmpty()->end()
                                ->integerNode('connection_timeout')->cannotBeEmpty()->end()
                            ->end()
                        ->end()  
                    ->end()
                ->end()
            ->end();
            

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
