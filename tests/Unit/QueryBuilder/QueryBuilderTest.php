<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Exception\EntityNotFoundException;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class QueryBuilderTest extends TestCase
{
    private QueryBuilder $queryBuilder;

    /**
     * @var MockObject&EntityDefinitionResolver
     */
    private MockObject $definitionResolver;

    /**
     * @var MockObject&PropertyResolver
     */
    private MockObject $propertyResolver;

    /**
     * @var MockObject&AssociationResolver
     */
    private MockObject $associationResolver;

    private FilterFactory $filterFactory;

    protected function setUp(): void
    {
        $this->definitionResolver = $this->createMock(EntityDefinitionResolver::class);
        $this->propertyResolver = $this->createMock(PropertyResolver::class);
        $this->associationResolver = $this->createMock(AssociationResolver::class);
        $this->filterFactory = new FilterFactory();

        $this->definitionResolver
            ->method('getDefinition')
            ->willReturn($this->createMock(ProductDefinition::class));

        $this->queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory
        );
    }

    public function testGetEntityClass(): void
    {
        $this->assertSame(ProductEntity::class, $this->queryBuilder->getEntityClass());
    }

    public function testSetAlias(): void
    {
        $result = $this->queryBuilder->setAlias('p');

        $this->assertSame($this->queryBuilder, $result);
    }

    public function testWhereAddsSimpleCondition(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->with(ProductEntity::class, 'active')
            ->willReturn('active');

        $this->queryBuilder->where('active', true);

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertCount(1, $expressions);
        $this->assertSame('active', $expressions[0]->getField());
        $this->assertSame('=', $expressions[0]->getOperator());
        $this->assertTrue($expressions[0]->getValue());
    }

    public function testWhereAddsConditionWithOperator(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->with(ProductEntity::class, 'stock')
            ->willReturn('stock');

        $this->queryBuilder->where('stock', '>', 10);

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertCount(1, $expressions);
        $this->assertSame('stock', $expressions[0]->getField());
        $this->assertSame('>', $expressions[0]->getOperator());
        $this->assertSame(10, $expressions[0]->getValue());
    }

    public function testMultipleWhere(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder
            ->where('active', true)
            ->where('stock', '>', 0)
            ->where('price', '>=', 10);

        $this->assertCount(3, $this->queryBuilder->getWhereExpressions());
    }

    public function testOrWhere(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->orWhere(function (QueryBuilder $q): void {
            $q->where('stock', '>', 10)
                ->where('featured', true);
        });

        $orGroups = $this->queryBuilder->getOrWhereGroups();

        $this->assertCount(1, $orGroups);
        $this->assertCount(2, $orGroups[0]);
    }

    public function testWithSimpleAssociation(): void
    {
        $this->associationResolver
            ->method('resolve')
            ->with(ProductEntity::class, 'manufacturer')
            ->willReturn([
                'path' => 'manufacturer',
                'entity' => 'ManufacturerEntity',
                'propertyName' => 'manufacturer',
            ]);

        $this->queryBuilder->with('manufacturer');

        $associations = $this->queryBuilder->getAssociations();

        $this->assertArrayHasKey('manufacturer', $associations);
        $this->assertNull($associations['manufacturer']);
    }

    public function testWithAssociationAndAlias(): void
    {
        $this->associationResolver
            ->method('resolve')
            ->with(ProductEntity::class, 'manufacturer')
            ->willReturn([
                'path' => 'manufacturer',
                'entity' => 'ManufacturerEntity',
                'propertyName' => 'manufacturer',
            ]);

        $this->queryBuilder->with('manufacturer', 'm');

        $associations = $this->queryBuilder->getAssociations();

        $this->assertArrayHasKey('manufacturer', $associations);
    }

    public function testWithAssociationAndCallback(): void
    {
        $this->associationResolver
            ->method('resolve')
            ->willReturn([
                'path' => 'manufacturer',
                'entity' => 'ManufacturerEntity',
                'propertyName' => 'manufacturer',
            ]);

        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->with('manufacturer', function (QueryBuilder $q): void {
            $q->where('active', true);
        });

        $associations = $this->queryBuilder->getAssociations();

        $this->assertArrayHasKey('manufacturer', $associations);
        $this->assertInstanceOf(QueryBuilder::class, $associations['manufacturer']);
    }

    public function testOrderBy(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->with(ProductEntity::class, 'name')
            ->willReturn('name');

        $this->queryBuilder->orderBy('name', 'ASC');

        $sortings = $this->queryBuilder->getSortings();

        $this->assertCount(1, $sortings);
        $this->assertSame('name', $sortings[0]['field']);
        $this->assertSame('ASC', $sortings[0]['direction']);
    }

    public function testOrderByDefaultDirection(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->orderBy('name');

        $sortings = $this->queryBuilder->getSortings();

        $this->assertSame('ASC', $sortings[0]['direction']);
    }

    public function testLimit(): void
    {
        $this->queryBuilder->limit(10);

        $this->assertSame(10, $this->queryBuilder->getLimit());
    }

    public function testOffset(): void
    {
        $this->queryBuilder->offset(20);

        $this->assertSame(20, $this->queryBuilder->getOffset());
    }

    public function testPaginate(): void
    {
        $this->queryBuilder->paginate(2, 15);

        $this->assertSame(2, $this->queryBuilder->getPage());
        $this->assertSame(15, $this->queryBuilder->getPerPage());
        $this->assertSame(15, $this->queryBuilder->getLimit());
        $this->assertSame(15, $this->queryBuilder->getOffset()); // (2-1) * 15
    }

    public function testWhereBetween(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereBetween('price', 10, 100);

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertCount(2, $expressions);
        $this->assertSame('>=', $expressions[0]->getOperator());
        $this->assertSame(10, $expressions[0]->getValue());
        $this->assertSame('<=', $expressions[1]->getOperator());
        $this->assertSame(100, $expressions[1]->getValue());
    }

    public function testWhereIn(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $ids = ['id1', 'id2', 'id3'];
        $this->queryBuilder->whereIn('id', $ids);

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertCount(1, $expressions);
        $this->assertSame('in', $expressions[0]->getOperator());
        $this->assertSame($ids, $expressions[0]->getValue());
    }

    public function testWhereNotIn(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereNotIn('id', ['id1', 'id2']);

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertSame('not in', $expressions[0]->getOperator());
    }

    public function testWhereNull(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereNull('deletedAt');

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertSame('is null', $expressions[0]->getOperator());
        $this->assertNull($expressions[0]->getValue());
    }

    public function testWhereNotNull(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereNotNull('parentId');

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertSame('is not null', $expressions[0]->getOperator());
    }

    public function testWhereStartsWith(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereStartsWith('productNumber', 'SW-');

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertSame('starts with', $expressions[0]->getOperator());
        $this->assertSame('SW-', $expressions[0]->getValue());
    }

    public function testWhereEndsWith(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereEndsWith('productNumber', '-001');

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertSame('ends with', $expressions[0]->getOperator());
        $this->assertSame('-001', $expressions[0]->getValue());
    }

    public function testToCriteria(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder
            ->where('active', true)
            ->limit(10);

        $criteria = $this->queryBuilder->toCriteria();

        $this->assertInstanceOf(Criteria::class, $criteria);
        $this->assertSame(10, $criteria->getLimit());
    }

    public function testGetThrowsExceptionWhenNotConfiguredForExecution(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not configured for execution');

        $this->queryBuilder->get();
    }

    public function testGetOneOrThrowThrowsEntityNotFoundException(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn(null);

        $repository
            ->method('search')
            ->willReturn($searchResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $this->expectException(EntityNotFoundException::class);

        $this->queryBuilder->getOneOrThrow();
    }

    public function testCount(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $idResult = $this->createMock(IdSearchResult::class);
        $idResult->method('getTotal')->willReturn(42);

        $repository
            ->method('searchIds')
            ->willReturn($idResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $this->assertSame(42, $this->queryBuilder->count());
    }

    public function testExists(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $idResult = $this->createMock(IdSearchResult::class);
        $idResult->method('getTotal')->willReturn(5);

        $repository
            ->method('searchIds')
            ->willReturn($idResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $this->assertTrue($this->queryBuilder->exists());
    }

    public function testDoesntExist(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $idResult = $this->createMock(IdSearchResult::class);
        $idResult->method('getTotal')->willReturn(0);

        $repository
            ->method('searchIds')
            ->willReturn($idResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $this->assertTrue($this->queryBuilder->doesntExist());
    }

    public function testGetPaginatedThrowsExceptionWhenNotPaginated(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('paginate() must be called');

        $this->queryBuilder->getPaginated();
    }
}
