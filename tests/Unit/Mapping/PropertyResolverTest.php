<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidPropertyException;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;

#[CoversClass(PropertyResolver::class)]
class PropertyResolverTest extends TestCase
{
    private PropertyResolver $resolver;

    private MockObject $definitionResolver;

    protected function setUp(): void
    {
        $this->definitionResolver = $this->createMock(EntityDefinitionResolver::class);
        $this->resolver = new PropertyResolver($this->definitionResolver);
    }

    public function testResolveSimpleProperty(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->with(ProductEntity::class, 'active')
            ->willReturn(true);

        $result = $this->resolver->resolve(ProductEntity::class, 'active');

        $this->assertSame('active', $result);
    }

    public function testResolveThrowsExceptionForInvalidProperty(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->with(ProductEntity::class, 'invalid')
            ->willReturn(false);

        $this->definitionResolver
            ->method('getAvailableFields')
            ->willReturn(['id', 'name', 'active', 'stock']);

        $this->expectException(InvalidPropertyException::class);
        $this->expectExceptionMessageMatches("/Property 'invalid' does not exist/");

        $this->resolver->resolve(ProductEntity::class, 'invalid');
    }

    public function testResolveNestedProperty(): void
    {
        // First level: check 'manufacturer' is an association
        $this->definitionResolver
            ->method('isAssociation')
            ->willReturnMap([
                [ProductEntity::class, 'manufacturer', true],
            ]);

        // Get association info
        $this->definitionResolver
            ->method('getAssociationInfo')
            ->willReturn([
                'propertyName' => 'manufacturer',
                'referenceClass' => 'ManufacturerEntity',
                'type' => 'many_to_one',
            ]);

        // Second level: check 'name' is a field
        $this->definitionResolver
            ->method('hasField')
            ->with('ManufacturerEntity', 'name')
            ->willReturn(true);

        $result = $this->resolver->resolve(ProductEntity::class, 'manufacturer.name');

        $this->assertSame('manufacturer.name', $result);
    }

    public function testResolveNestedPropertyThrowsExceptionForInvalidAssociation(): void
    {
        $this->definitionResolver
            ->method('isAssociation')
            ->with(ProductEntity::class, 'invalid')
            ->willReturn(false);

        $this->definitionResolver
            ->method('getAvailableAssociations')
            ->willReturn(['manufacturer', 'categories', 'tax']);

        $this->expectException(InvalidPropertyException::class);
        $this->expectExceptionMessageMatches("/'invalid' is not an association/");

        $this->resolver->resolve(ProductEntity::class, 'invalid.name');
    }

    public function testResolveNestedPropertyThrowsExceptionForInvalidFinalProperty(): void
    {
        $this->definitionResolver
            ->method('isAssociation')
            ->with(ProductEntity::class, 'manufacturer')
            ->willReturn(true);

        $this->definitionResolver
            ->method('getAssociationInfo')
            ->willReturn([
                'propertyName' => 'manufacturer',
                'referenceClass' => 'ManufacturerEntity',
                'type' => 'many_to_one',
            ]);

        $this->definitionResolver
            ->method('hasField')
            ->with('ManufacturerEntity', 'invalid')
            ->willReturn(false);

        $this->definitionResolver
            ->method('getAvailableFields')
            ->willReturn(['id', 'name', 'active']);

        $this->expectException(InvalidPropertyException::class);
        $this->expectExceptionMessageMatches("/Property 'invalid' does not exist/");

        $this->resolver->resolve(ProductEntity::class, 'manufacturer.invalid');
    }
}
