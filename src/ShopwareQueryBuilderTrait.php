<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder;

use Algoritma\ShopwareQueryBuilder\DependencyInjection\Compiler\QueryHelperPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait ShopwareQueryBuilderTrait
{
    public function setGlobalQueryHelperLocator(?ContainerInterface $container): void
    {
        global $queryHelperServiceLocator;

        if ($queryHelperServiceLocator === null && $container instanceof ContainerInterface) {
            $queryHelperServiceLocator = $container->get('algoritma.query_builder.service_locator');
        }
    }

    public function addGlobalQuryHelperCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new QueryHelperPass());
    }
}
