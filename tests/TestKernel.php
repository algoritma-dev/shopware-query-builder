<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests;

use Algoritma\ShopwareQueryBuilder\ShopwareQueryBuilderBundle;
use Pentatrion\ViteBundle\PentatrionViteBundle;
use Shopware\Administration\Administration;
use Shopware\Core\Checkout\Checkout;
use Shopware\Core\Content\Content;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\Maintenance;
use Shopware\Core\Profiling\Profiling;
use Shopware\Core\Service\Service;
use Shopware\Core\System\System;
use Shopware\Elasticsearch\Elasticsearch;
use Shopware\Storefront\Storefront;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test kernel for integration tests with real database.
 */
class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield from [
            new FrameworkBundle(),
            new TwigBundle(),
            new MonologBundle(),
            new Framework(),
            new System(),
            new Content(),
            new Checkout(),
            new Maintenance(),
            new Profiling(),
            new Administration(),
            new Elasticsearch(),
            new Storefront(),
            new WebProfilerBundle(),
            new PentatrionViteBundle(),
            new Service(),
            new ShopwareQueryBuilderBundle(),
        ];
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);
    }
}
