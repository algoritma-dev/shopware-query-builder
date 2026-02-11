<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Integration tests that verify the entire query building pipeline.
 */
class QueryBuilderIntegrationTest extends TestCase
{
    /**
     * @var MockObject&EntityDefinitionResolver
     */
    private MockObject $definitionResolver;

    private PropertyResolver $propertyResolver;

    private AssociationResolver $associationResolver;

    private FilterFactory $filterFactory;

    protected function setUp(): void
    {
        $this->definitionResolver = $this->createMock(EntityDefinitionResolver::class);
        $this->filterFactory = new FilterFactory();

        $this->definitionResolver
            ->method('getDefinition')
            ->willReturn($this->createMock(ProductDefinition::class));

        $this->propertyResolver = new PropertyResolver($this->definitionResolver);
        $this->associationResolver = new AssociationResolver($this->definitionResolver);
    }

    public function testSimpleQueryToCriteria(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->willReturn(true);

        $queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            new RawExpressionParser()
        );

        $queryBuilder->where('active = true');

        $criteria = $queryBuilder->toCriteria();

        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertCount(1, $criteria->getFilters());
    }

    public function testComplexQueryToCriteria(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->willReturn(true);

        $this->definitionResolver
            ->method('isAssociation')
            ->willReturn(true);

        $this->definitionResolver
            ->method('getAssociationInfo')
            ->willReturn([
                'propertyName' => 'manufacturer',
                'referenceClass' => 'ManufacturerEntity',
                'type' => 'many_to_one',
            ]);

        $queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            new RawExpressionParser()
        );

        $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->with('manufacturer')
            ->orderBy('name', 'ASC')
            ->limit(10)
            ->offset(20);

        $criteria = $queryBuilder->toCriteria();

        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertCount(1, $criteria->getFilters());
        $this->assertTrue($criteria->hasAssociation('manufacturer'));
        $this->assertCount(1, $criteria->getSorting());
        $this->assertSame(10, $criteria->getLimit());
        $this->assertSame(20, $criteria->getOffset());
    }

    public function testQueryWithMultipleOperators(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->willReturn(true);

        $queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            new RawExpressionParser()
        );

        $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->where('price >= 10')
            ->where('price <= 100')
            ->where('name LIKE "Test"');

        $criteria = $queryBuilder->toCriteria();

        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertCount(1, $criteria->getFilters());
    }

    public function testQueryWithConvenienceMethods(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->willReturn(true);

        $queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            new RawExpressionParser()
        );

        $queryBuilder
            ->where('active = true')
            ->whereBetween('price', 10, 100)
            ->whereIn('id', ['id1', 'id2'])
            ->whereNotNull('parentId')
            ->whereStartsWith('productNumber', 'SW-');

        $criteria = $queryBuilder->toCriteria();

        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertGreaterThan(0, count($criteria->getFilters()));
    }

    public function testQueryWithPagination(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->willReturn(true);

        $queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            new RawExpressionParser()
        );

        $queryBuilder
            ->where('active = true')
            ->paginate(2, 20);

        $this->assertSame(2, $queryBuilder->getPage());
        $this->assertSame(20, $queryBuilder->getPerPage());
        $this->assertSame(20, $queryBuilder->getLimit());
        $this->assertSame(20, $queryBuilder->getOffset()); // (2-1) * 20

        $criteria = $queryBuilder->toCriteria();

        $this->assertSame(20, $criteria->getLimit());
        $this->assertSame(20, $criteria->getOffset());
    }

    public function testQueryWithNestedAssociations(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->willReturn(true);

        $this->definitionResolver
            ->method('isAssociation')
            ->willReturn(true);

        $this->definitionResolver
            ->method('getAssociationInfo')
            ->willReturnOnConsecutiveCalls(
                [
                    'propertyName' => 'manufacturer',
                    'referenceClass' => 'ManufacturerEntity',
                    'type' => 'many_to_one',
                ],
                [
                    'propertyName' => 'categories',
                    'referenceClass' => 'CategoryEntity',
                    'type' => 'many_to_many',
                ]
            );

        $queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            new RawExpressionParser()
        );

        $queryBuilder
            ->with('manufacturer')
            ->with('categories');

        $criteria = $queryBuilder->toCriteria();

        $this->assertTrue($criteria->hasAssociation('manufacturer'));
        $this->assertTrue($criteria->hasAssociation('categories'));
    }

    public function testFluentInterfaceReturnsQueryBuilder(): void
    {
        $this->definitionResolver
            ->method('hasField')
            ->willReturn(true);

        $this->definitionResolver
            ->method('isAssociation')
            ->willReturn(true);

        $this->definitionResolver
            ->method('getAssociationInfo')
            ->willReturn([
                'propertyName' => 'manufacturer',
                'referenceClass' => 'ManufacturerEntity',
                'type' => 'many_to_one',
            ]);

        $queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            new RawExpressionParser()
        );

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->with('manufacturer')
            ->orderBy('name', 'ASC')
            ->limit(10);

        $this->assertSame($queryBuilder, $result);
    }
}
