<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shopware Query Builder Plugin.
 *
 * Integrates the Query Builder library into Shopware 6
 */
class AlgoritmaShopwareQueryBuilder extends Plugin
{
    use ShopwareQueryBuilderTrait;

    public function setContainer(?ContainerInterface $container): void
    {
        parent::setContainer($container);

        $this->setGlobalQueryHelperLocator($container);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->addGlobalQuryHelperCompilerPass($container);
    }
}
