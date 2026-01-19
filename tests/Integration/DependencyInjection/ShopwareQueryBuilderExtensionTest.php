<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration\DependencyInjection;

use Algoritma\ShopwareQueryBuilder\DependencyInjection\ShopwareQueryBuilderExtension;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Integration tests for ShopwareQueryBuilderExtension configuration.
 */
class ShopwareQueryBuilderExtensionTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
    }

    /**
     * Test that entity mappings can be registered through configuration.
     *
     * This tests the ability to support attribute-based entities through YAML configuration.
     */
    public function testAttributeEntityMappingCanBeConfigured(): void
    {
        $extension = new ShopwareQueryBuilderExtension();

        // Mock configuration with attribute entities
        $configs = [
            [
                'attribute_entities' => [
                    'MyPlugin\Entities\CustomProductEntity' => 'custom_product',
                    'MyPlugin\Entities\CustomCategoryEntity' => 'custom_category',
                ],
            ],
        ];

        // Mock the DefinitionInstanceRegistry and EntityDefinitionResolver
        $this->container->set(DefinitionInstanceRegistry::class, $this->createMock(DefinitionInstanceRegistry::class));

        // Register the EntityDefinitionResolver service manually since we can't load XML
        $this->container->register(EntityDefinitionResolver::class)
            ->addArgument($this->container->get(DefinitionInstanceRegistry::class));

        // Load the extension configuration
        $extension->load($configs, $this->container);

        // Get the EntityDefinitionResolver definition
        $resolverDef = $this->container->getDefinition(EntityDefinitionResolver::class);

        // Verify that method calls for entity mapping have been added
        $methodCalls = $resolverDef->getMethodCalls();

        // Should have 2 method calls for the 2 entity mappings
        $this->assertGreaterThanOrEqual(2, count($methodCalls));

        // Check that registerEntityMapping methods are present
        $mappingCalls = array_filter(
            $methodCalls,
            fn (array $call): bool => $call[0] === 'registerEntityMapping'
        );

        $this->assertCount(2, $mappingCalls);
    }
}
