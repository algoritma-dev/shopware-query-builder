<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration for Shopware Query Builder Bundle.
 *
 * Allows configuration of attribute-based entities through YAML config.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('shopware_query_builder');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->arrayNode('attribute_entities')
            ->info('Register entity name mappings for attribute-based entities')
            ->useAttributeAsKey('class')
            ->scalarPrototype()->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
