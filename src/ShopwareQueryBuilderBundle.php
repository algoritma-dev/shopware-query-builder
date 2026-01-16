<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder;

use Algoritma\ShopwareQueryBuilder\DependencyInjection\Compiler\QueryHelperPass;
use Shopware\Core\Framework\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shopware Query Builder Bundle.
 *
 * Integrates the Query Builder library into Shopware 6.7+
 * Services are automatically loaded from Resources/config/services.xml
 */
class ShopwareQueryBuilderBundle extends Bundle
{
    public function setContainer(?ContainerInterface $container): void
    {
        parent::setContainer($container);

        global $queryHelperServiceLocator;

        if ($queryHelperServiceLocator === null && $container instanceof ContainerInterface) {
            $queryHelperServiceLocator = $container->get('algoritma.query_builder.service_locator');
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new QueryHelperPass());
    }
}
