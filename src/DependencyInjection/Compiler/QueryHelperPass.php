<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\DependencyInjection\Compiler;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilderFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class QueryHelperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Create a service locator with only the services needed by the helper
        $container->register('algoritma.query_builder.service_locator', ServiceLocator::class)
            ->setPublic(true)
            ->setArguments([[
                'query_factory' => new Reference(QueryBuilderFactory::class),
            ]])
            ->addTag('container.service_locator');

        // Load the helpers file
        if (! function_exists('query')) {
            require_once __DIR__ . '/../../helpers.php';
        }
    }
}
