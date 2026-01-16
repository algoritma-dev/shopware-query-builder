<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidPropertyException;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;

class AssociationResolverTest extends TestCase
{
    private AssociationResolver $resolver;

    private MockObject $definitionResolver;

    protected function setUp(): void
    {
        $this->definitionResolver = $this->createMock(EntityDefinitionResolver::class);
        $this->resolver = new AssociationResolver($this->definitionResolver);
    }

    public function testResolveSimpleAssociation(): void
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

        $result = $this->resolver->resolve(ProductEntity::class, 'manufacturer');

        $this->assertSame([
            'path' => 'manufacturer',
            'entity' => 'ManufacturerEntity',
            'propertyName' => 'manufacturer',
        ], $result);
    }

    public function testResolveThrowsExceptionForInvalidAssociation(): void
    {
        $this->definitionResolver
            ->method('isAssociation')
            ->with(ProductEntity::class, 'invalid')
            ->willReturn(false);

        $this->definitionResolver
            ->method('getAvailableAssociations')
            ->willReturn(['manufacturer', 'categories', 'tax']);

        $this->expectException(InvalidPropertyException::class);
        $this->expectExceptionMessageMatches("/Association 'invalid' does not exist/");

        $this->resolver->resolve(ProductEntity::class, 'invalid');
    }

    public function testResolveNestedAssociation(): void
    {
        // First level
        $this->definitionResolver
            ->method('isAssociation')
            ->willReturnMap([
                [ProductEntity::class, 'manufacturer', true],
                ['ManufacturerEntity', 'country', true],
            ]);

        $this->definitionResolver
            ->method('getAssociationInfo')
            ->willReturnOnConsecutiveCalls(
                [
                    'propertyName' => 'manufacturer',
                    'referenceClass' => 'ManufacturerEntity',
                    'type' => 'many_to_one',
                ],
                [
                    'propertyName' => 'country',
                    'referenceClass' => 'CountryEntity',
                    'type' => 'many_to_one',
                ]
            );

        $result = $this->resolver->resolve(ProductEntity::class, 'manufacturer.country');

        $this->assertSame([
            'path' => 'manufacturer.country',
            'entity' => 'CountryEntity',
            'propertyName' => 'country',
        ], $result);
    }

    public function testResolveNestedThrowsExceptionForInvalidIntermediateAssociation(): void
    {
        $this->definitionResolver
            ->method('isAssociation')
            ->with(ProductEntity::class, 'invalid')
            ->willReturn(false);

        $this->definitionResolver
            ->method('getAvailableAssociations')
            ->willReturn(['manufacturer', 'categories']);

        $this->expectException(InvalidPropertyException::class);
        $this->expectExceptionMessageMatches("/Association 'invalid' does not exist/");

        $this->resolver->resolve(ProductEntity::class, 'invalid.country');
    }
}
