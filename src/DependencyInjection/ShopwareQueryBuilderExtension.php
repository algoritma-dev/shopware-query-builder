<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\DependencyInjection;

use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * DependencyInjection Extension for Shopware Query Builder.
 *
 * Supports configuration of attribute-based entities through the bundle config.
 */
class ShopwareQueryBuilderExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        // Register entity name mappings for attribute-based entities
        if (! empty($config['attribute_entities'])) {
            $resolverDefinition = $container->getDefinition(EntityDefinitionResolver::class);

            foreach ($config['attribute_entities'] as $entityClass => $entityName) {
                $resolverDefinition->addMethodCall('registerEntityMapping', [
                    $entityClass,
                    $entityName,
                ]);
            }
        }
    }
}
