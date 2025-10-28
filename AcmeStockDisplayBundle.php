<?php

namespace Acme\Bundle\StockDisplayBundle;

use Acme\Bundle\StockDisplayBundle\DependencyInjection\CompilerPass\MakeInventoryServicePublicPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AcmeStockDisplayBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new MakeInventoryServicePublicPass());
    }
}
