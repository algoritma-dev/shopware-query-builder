<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\CriteriaBuilder;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CriteriaBuilderTest extends TestCase
{
    private CriteriaBuilder $criteriaBuilder;

    private FilterFactory $filterFactory;

    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->filterFactory = new FilterFactory();
        $this->criteriaBuilder = new CriteriaBuilder($this->filterFactory);

        $definitionResolver = $this->createMock(EntityDefinitionResolver::class);
        $propertyResolver = $this->createMock(PropertyResolver::class);
        $associationResolver = $this->createMock(AssociationResolver::class);

        $definitionResolver
            ->method('getDefinition')
            ->willReturn($this->createMock(ProductDefinition::class));

        $propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $associationResolver
            ->method('resolve')
            ->willReturnCallback(fn (string $entity, string $assoc): array => [
                'path' => $assoc,
                'entity' => 'SomeEntity',
                'propertyName' => $assoc,
            ]);

        $this->queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $definitionResolver,
            $propertyResolver,
            $associationResolver,
            $this->filterFactory
        );
    }

    public function testBuildReturnsCriteria(): void
    {
        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertInstanceOf(Criteria::class, $criteria);
    }

    public function testBuildWithWhereFilters(): void
    {
        $this->queryBuilder->where('active', true);

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertCount(1, $criteria->getFilters());
    }

    public function testBuildWithMultipleWhereFilters(): void
    {
        $this->queryBuilder
            ->where('active', true)
            ->where('stock', '>', 0)
            ->where('price', '>=', 10);

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        // Should be wrapped in a MultiFilter with AND connection
        $this->assertCount(1, $criteria->getFilters());
    }

    public function testBuildWithAssociations(): void
    {
        $this->queryBuilder->with('manufacturer');

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertTrue($criteria->hasAssociation('manufacturer'));
    }

    public function testBuildWithMultipleAssociations(): void
    {
        $this->queryBuilder
            ->with('manufacturer')
            ->with('categories');

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertTrue($criteria->hasAssociation('manufacturer'));
        $this->assertTrue($criteria->hasAssociation('categories'));
    }

    public function testBuildWithSortings(): void
    {
        $this->queryBuilder->orderBy('name', 'ASC');

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $sortings = $criteria->getSorting();

        $this->assertCount(1, $sortings);
    }

    public function testBuildWithMultipleSortings(): void
    {
        $this->queryBuilder
            ->orderBy('name', 'ASC')
            ->orderBy('createdAt', 'DESC');

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertCount(2, $criteria->getSorting());
    }

    public function testBuildWithLimit(): void
    {
        $this->queryBuilder->limit(10);

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertSame(10, $criteria->getLimit());
    }

    public function testBuildWithOffset(): void
    {
        $this->queryBuilder->offset(20);

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertSame(20, $criteria->getOffset());
    }

    public function testBuildWithLimitAndOffset(): void
    {
        $this->queryBuilder
            ->limit(15)
            ->offset(30);

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertSame(15, $criteria->getLimit());
        $this->assertSame(30, $criteria->getOffset());
    }

    public function testBuildComplexQuery(): void
    {
        $this->queryBuilder
            ->where('active', true)
            ->where('stock', '>', 0)
            ->with('manufacturer')
            ->with('categories')
            ->orderBy('name', 'ASC')
            ->orderBy('createdAt', 'DESC')
            ->limit(20)
            ->offset(40);

        $criteria = $this->criteriaBuilder->build($this->queryBuilder);

        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertCount(1, $criteria->getFilters());
        $this->assertTrue($criteria->hasAssociation('manufacturer'));
        $this->assertTrue($criteria->hasAssociation('categories'));
        $this->assertCount(2, $criteria->getSorting());
        $this->assertSame(20, $criteria->getLimit());
        $this->assertSame(40, $criteria->getOffset());
    }
}
