<?php

namespace Webit\Accounting\PaymentCashbillBundle\DependencyInjection;

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
        $rootNode = $treeBuilder->root('webit_accounting_payment_cashbill');
        $rootNode->children()
            ->scalarNode('client_id')->isRequired()->end()
            ->scalarNode('pos_id')->isRequired()->end()
            ->scalarNode('private_key')->isRequired()->end()
            ->scalarNode('url')->defaultValue('https://www.cashbill.pl/cblite/pay.php')->canNotBeEmpty()->end()
            ->scalarNode('return_route')->isRequired()->end()
            ->scalarNode('test_mode')->isRequired()->defaultTrue()->end()
            ->scalarNode('timeout')->isRequired()->cannotBeEmpty()->defaultValue(10)->end()
        ->end();

        return $treeBuilder;
    }
}
