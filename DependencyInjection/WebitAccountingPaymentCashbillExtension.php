<?php

namespace Webit\Accounting\PaymentCashbillBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Definition;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class WebitAccountingPaymentCashbillExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $token = $container->getDefinition('webit_accounting_payment_cashbill.token');
        foreach(array('client_id','pos_id','private_key') as $key) {
            $token->addArgument($config[$key]);
        }
        
        foreach(array('url','test_mode','return_route') as $key) {
            $container->setParameter($this->getAlias().'.'.$key, $config[$key]);
        }
    }
}
