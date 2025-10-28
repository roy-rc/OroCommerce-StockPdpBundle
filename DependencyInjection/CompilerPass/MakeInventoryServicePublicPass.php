<?php

namespace Acme\Bundle\StockDisplayBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MakeInventoryServicePublicPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('oro_inventory.inventory.low_inventory_provider')) {
            $container->getDefinition('oro_inventory.inventory.low_inventory_provider')->setPublic(true);
        }
        
        if ($container->hasDefinition('oro_inventory.provider.inventory_status')) {
            $container->getDefinition('oro_inventory.provider.inventory_status')->setPublic(true);
        }
    }
}