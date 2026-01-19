<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidEntityException;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * Tests for attribute-based entity support in EntityDefinitionResolver.
 */
class AttributeBasedEntitySupportTest extends TestCase
{
    private EntityDefinitionResolver $resolver;

    /**
     * @var MockObject&DefinitionInstanceRegistry
     */
    private MockObject $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->resolver = new EntityDefinitionResolver($this->registry);
    }

    public function testCanRegisterCustomEntityMapping(): void
    {
        $mockDefinition = $this->createMock(EntityDefinition::class);

        $this->registry
            ->method('getByEntityName')
            ->with('custom_product')
            ->willReturn($mockDefinition);

        // Register mapping for attribute-based entity
        $this->resolver->registerEntityMapping('CustomProductEntity', 'custom_product');

        // Should now resolve using the registered mapping
        $result = $this->resolver->getDefinition('CustomProductEntity');

        $this->assertSame($mockDefinition, $result);
    }

    public function testRegisterEntityMappingInvalidatesCache(): void
    {
        $mockDefinition1 = $this->createMock(EntityDefinition::class);
        $mockDefinition2 = $this->createMock(EntityDefinition::class);

        $this->registry
            ->expects($this->exactly(2))
            ->method('getByEntityName')
            ->willReturnOnConsecutiveCalls($mockDefinition1, $mockDefinition2);

        // First mapping and resolution
        $this->resolver->registerEntityMapping('CustomEntity', 'entity_one');
        $result1 = $this->resolver->getDefinition('CustomEntity');
        $this->assertSame($mockDefinition1, $result1);

        // Re-register with different mapping
        $this->resolver->registerEntityMapping('CustomEntity', 'entity_two');
        $result2 = $this->resolver->getDefinition('CustomEntity');

        // Should get the new definition (cache was invalidated)
        $this->assertSame($mockDefinition2, $result2);
    }

    public function testThrowsExceptionForUnresolvedEntity(): void
    {
        $this->expectException(InvalidEntityException::class);
        $this->expectExceptionMessageMatches('/Could not resolve entity name/');

        // Try to resolve entity without mapping or convention
        $this->resolver->getDefinition('UnregisteredEntity');
    }

    public function testMappingTakesPrecedenceOverConvention(): void
    {
        $mockDefinition = $this->createMock(EntityDefinition::class);

        $this->registry
            ->method('getByEntityName')
            ->with('mapped_entity')
            ->willReturn($mockDefinition);

        // Register a custom mapping
        $this->resolver->registerEntityMapping('ProductEntity', 'mapped_entity');

        // Should use the mapping instead of trying convention (ProductDefinition)
        $result = $this->resolver->getDefinition('ProductEntity');

        $this->assertSame($mockDefinition, $result);
    }
}
