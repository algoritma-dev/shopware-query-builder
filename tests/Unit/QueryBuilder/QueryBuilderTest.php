<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Exception\EntityNotFoundException;
use Algoritma\ShopwareQueryBuilder\Exception\UpdateEntityException;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\GroupExpression;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\WhereExpression;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Filter\OperatorMapper;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\CriteriaBuilder;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use Algoritma\ShopwareQueryBuilder\Scope\ActiveScope;
use Algoritma\ShopwareQueryBuilder\Scope\ScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(QueryBuilder::class)]
#[UsesClass(FilterFactory::class)]
#[UsesClass(RawExpressionParser::class)]
#[UsesClass(CriteriaBuilder::class)]
#[UsesClass(OperatorMapper::class)]
#[UsesClass(WhereExpression::class)]
#[UsesClass(GroupExpression::class)]
#[UsesClass(ActiveScope::class)]
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
            $this->filterFactory,
            new RawExpressionParser()
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

        $this->queryBuilder->where('active = true');

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

        $this->queryBuilder->where('stock > 10');

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
            ->where('active = true')
            ->where('stock > 0')
            ->where('price >= 10');

        $this->assertCount(3, $this->queryBuilder->getWhereExpressions());
    }

    public function testOrWhere(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->orWhere(function (QueryBuilder $q): void {
            $q->where('stock > 10')
                ->where('featured = true');
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
            $q->where('active = true');
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

        $this->assertSame('like', $expressions[0]->getOperator());
        $this->assertSame('SW-%', $expressions[0]->getValue());
    }

    public function testWhereEndsWith(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereEndsWith('productNumber', '-001');

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertSame('like', $expressions[0]->getOperator());
        $this->assertSame('%-001', $expressions[0]->getValue());
    }

    public function testToCriteria(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder
            ->where('active = true')
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

    // Aggregation tests

    public function testAddCount(): void
    {
        $this->queryBuilder->addCount('id', 'totalProducts');

        $aggregations = $this->queryBuilder->getAggregations();

        $this->assertCount(1, $aggregations);
        $this->assertArrayHasKey('totalProducts', $aggregations);
    }

    public function testAddSum(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->addSum('stock', 'totalStock');

        $aggregations = $this->queryBuilder->getAggregations();

        $this->assertArrayHasKey('totalStock', $aggregations);
    }

    public function testAddAvg(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->addAvg('price', 'avgPrice');

        $aggregations = $this->queryBuilder->getAggregations();

        $this->assertArrayHasKey('avgPrice', $aggregations);
    }

    public function testAddMin(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->addMin('price', 'minPrice');

        $aggregations = $this->queryBuilder->getAggregations();

        $this->assertArrayHasKey('minPrice', $aggregations);
    }

    public function testAddMax(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->addMax('price', 'maxPrice');

        $aggregations = $this->queryBuilder->getAggregations();

        $this->assertArrayHasKey('maxPrice', $aggregations);
    }

    // Grouping tests

    public function testWhereGroup(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereGroup(function (QueryBuilder $q): void {
            $q->where('stock > 10')
                ->where('active = true');
        });

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertCount(1, $expressions);
        $this->assertInstanceOf(GroupExpression::class, $expressions[0]);
        $this->assertSame('AND', $expressions[0]->getOperator());
        $this->assertCount(2, $expressions[0]->getExpressions());
    }

    public function testOrWhereGroup(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->orWhereGroup(function (QueryBuilder $q): void {
            $q->where('featured = true')
                ->where('onSale = true');
        });

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertCount(1, $expressions);
        $this->assertInstanceOf(GroupExpression::class, $expressions[0]);
        $this->assertSame('OR', $expressions[0]->getOperator());
    }

    public function testNestedWhereGroups(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder
            ->where('active = true')
            ->whereGroup(function (QueryBuilder $q): void {
                $q->where('stock > 0')
                    ->orWhereGroup(function (QueryBuilder $nested): void {
                        $nested->where('featured = true');
                    });
            });

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertCount(2, $expressions);
        $this->assertInstanceOf(GroupExpression::class, $expressions[1]);
    }

    // Scope tests

    public function testScope(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $scope = new ActiveScope();
        $this->queryBuilder->scope($scope);

        $expressions = $this->queryBuilder->getWhereExpressions();

        $this->assertCount(1, $expressions);
    }

    public function testScopes(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $scope1 = $this->createMock(ScopeInterface::class);
        $scope1->expects($this->once())->method('apply');

        $scope2 = $this->createMock(ScopeInterface::class);
        $scope2->expects($this->once())->method('apply');

        $this->queryBuilder->scopes([$scope1, $scope2]);
    }

    // Soft Delete tests

    public function testWithTrashed(): void
    {
        $result = $this->queryBuilder->withTrashed();

        $this->assertSame($this->queryBuilder, $result);
    }

    public function testOnlyTrashed(): void
    {
        $result = $this->queryBuilder->onlyTrashed();

        $this->assertSame($this->queryBuilder, $result);
    }

    public function testWithoutTrashed(): void
    {
        $result = $this->queryBuilder->withoutTrashed();

        $this->assertSame($this->queryBuilder, $result);
    }

    public function testToCriteriaAppliesSoftDeleteFilters(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->onlyTrashed();

        $criteria = $this->queryBuilder->toCriteria();

        $this->assertInstanceOf(Criteria::class, $criteria);
    }

    // Debugging tests

    public function testDebug(): void
    {
        $result = $this->queryBuilder->debug();

        $this->assertSame($this->queryBuilder, $result);
    }

    public function testDump(): void
    {
        ob_start();
        $result = $this->queryBuilder->dump();
        $output = ob_get_clean();

        $this->assertSame($this->queryBuilder, $result);
        $this->assertStringContainsString('Query Builder Debug', $output);
    }

    public function testDd(): void
    {
        // dd() calls exit(1), which we cannot test in unit tests
        // We just ensure the method is callable by checking it exists
        $this->assertIsCallable($this->queryBuilder->dd(...));
    }

    public function testToDebugArray(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder
            ->where('active = true')
            ->limit(10)
            ->orderBy('name');

        $debugArray = $this->queryBuilder->toDebugArray();

        $this->assertArrayHasKey('entity', $debugArray);
        $this->assertArrayHasKey('where', $debugArray);
        $this->assertArrayHasKey('limit', $debugArray);
        $this->assertArrayHasKey('orderBy', $debugArray);
        $this->assertSame(ProductEntity::class, $debugArray['entity']);
        $this->assertSame(10, $debugArray['limit']);
    }

    public function testToDebugArrayWithAlias(): void
    {
        $this->queryBuilder->setAlias('p');

        $debugArray = $this->queryBuilder->toDebugArray();

        $this->assertSame('p', $debugArray['alias']);
    }

    public function testToDebugArrayWithAggregations(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder
            ->addCount('id', 'total')
            ->addSum('stock', 'totalStock');

        $debugArray = $this->queryBuilder->toDebugArray();

        $this->assertCount(2, $debugArray['aggregations']);
        $this->assertContains('total', $debugArray['aggregations']);
        $this->assertContains('totalStock', $debugArray['aggregations']);
    }

    public function testToDebugArrayWithTrashedFlags(): void
    {
        $this->queryBuilder->withTrashed();

        $debugArray = $this->queryBuilder->toDebugArray();

        $this->assertTrue($debugArray['withTrashed']);
    }

    public function testToDebugArrayWithOnlyTrashedFlag(): void
    {
        $this->queryBuilder->onlyTrashed();

        $debugArray = $this->queryBuilder->toDebugArray();

        $this->assertTrue($debugArray['onlyTrashed']);
    }

    public function testToDebugArrayWithOrWhereGroups(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->orWhere(function (QueryBuilder $q): void {
            $q->where('featured = true');
        });

        $debugArray = $this->queryBuilder->toDebugArray();

        $this->assertIsArray($debugArray['orWhere']);
        $this->assertCount(1, $debugArray['orWhere']);
    }

    public function testToDebugArrayWithGroupExpressions(): void
    {
        $this->propertyResolver
            ->method('resolve')
            ->willReturnArgument(1);

        $this->queryBuilder->whereGroup(function (QueryBuilder $q): void {
            $q->where('active = true')
                ->where('stock > 0');
        });

        $debugArray = $this->queryBuilder->toDebugArray();

        $this->assertCount(1, $debugArray['where']);
        $this->assertSame('group', $debugArray['where'][0]['type']);
        $this->assertSame('AND', $debugArray['where'][0]['operator']);
        $this->assertIsArray($debugArray['where'][0]['group']);
    }

    // Additional execution method tests

    public function testGetEntities(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $entities = $this->createMock(EntityCollection::class);
        $searchResult->method('getEntities')->willReturn($entities);

        $repository
            ->method('search')
            ->willReturn($searchResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $result = $this->queryBuilder->getEntities();

        $this->assertSame($entities, $result);
    }

    public function testToArrayMethod(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $entities = $this->createMock(EntityCollection::class);
        $entities->method('getElements')->willReturn(['entity1', 'entity2']);
        $searchResult->method('getEntities')->willReturn($entities);

        $repository
            ->method('search')
            ->willReturn($searchResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $result = $this->queryBuilder->toArray();

        $this->assertSame(['entity1', 'entity2'], $result);
    }

    public function testGetIdsArray(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $idResult = $this->createMock(IdSearchResult::class);
        $idResult->method('getIds')->willReturn(['id1', 'id2', 'id3']);

        $repository
            ->method('searchIds')
            ->willReturn($idResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $result = $this->queryBuilder->getIdsArray();

        $this->assertSame(['id1', 'id2', 'id3'], $result);
    }

    public function testFirstAlias(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $entity = $this->createMock(ProductEntity::class);
        $searchResult->method('first')->willReturn($entity);

        $repository
            ->method('search')
            ->willReturn($searchResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $result = $this->queryBuilder->first();

        $this->assertSame($entity, $result);
    }

    public function testFirstOrFail(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $entity = $this->createMock(ProductEntity::class);
        $searchResult->method('first')->willReturn($entity);

        $repository
            ->method('search')
            ->willReturn($searchResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);

        $result = $this->queryBuilder->firstOrFail();

        $this->assertSame($entity, $result);
    }

    public function testGetPaginatedReturnsCorrectStructure(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $entities = $this->createMock(EntityCollection::class);
        $searchResult->method('getEntities')->willReturn($entities);
        $searchResult->method('getTotal')->willReturn(100);

        $repository
            ->method('search')
            ->willReturn($searchResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);
        $this->queryBuilder->paginate(2, 20);

        $result = $this->queryBuilder->getPaginated();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('lastPage', $result);
        $this->assertArrayHasKey('hasMorePages', $result);
        $this->assertSame(100, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(20, $result['perPage']);
        $this->assertSame(5, $result['lastPage']);
        $this->assertTrue($result['hasMorePages']);
    }

    public function testGetPaginatedLastPageHasNoMorePages(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $context = $this->createMock(Context::class);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $entities = $this->createMock(EntityCollection::class);
        $searchResult->method('getEntities')->willReturn($entities);
        $searchResult->method('getTotal')->willReturn(50);

        $repository
            ->method('search')
            ->willReturn($searchResult);

        $this->queryBuilder->setRepository($repository);
        $this->queryBuilder->setContext($context);
        $this->queryBuilder->paginate(3, 20);

        $result = $this->queryBuilder->getPaginated();

        $this->assertFalse($result['hasMorePages']);
    }

    public function testUpdateWithoutRepositoryShouldThrowError(): void
    {
        $context = $this->createMock(Context::class);
        $this->queryBuilder->setContext($context);

        $this->expectException(\RuntimeException::class);
        $this->queryBuilder->update([[]]);
    }

    public function testUpdateShouldReturnEntityUpdated(): void
    {
        $entityData = [
            'id' => 'entity-id',
            'name' => 'Updated Name',
        ];

        $expectedEntity = new ProductEntity();
        $expectedEntity->setId('entity-id');
        $expectedEntity->setName('Updated Name');

        $entities = new ProductCollection([$expectedEntity]);

        $result = $this->createMock(EntitySearchResult::class);
        $result->expects($this->once())
            ->method('getEntities')
            ->willReturn($entities);

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $event->expects($this->once())
            ->method('getPrimaryKeys')
            ->willReturn(['entity-id']);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with([$entityData])
            ->willReturn($event)
        ;
        $repository->expects($this->once())
            ->method('search')
            ->with(new Criteria([$entityData['id']]))
            ->willReturn($result);

        $context = $this->createMock(Context::class);
        $this->queryBuilder->setContext($context);
        $this->queryBuilder->setRepository($repository);

        $result = $this->queryBuilder->update([
            $entityData,
        ]);

        self::assertInstanceOf(ProductEntity::class, $result);
        self::assertSame('entity-id', $result->getId());
        self::assertSame('Updated Name', $result->getName());
    }

    public function testUpdateCanUpdateMultipleEntity(): void
    {
        $data = [
            [
                'id' => 'entity-id-1',
                'name' => 'Updated Name 1',
            ],
            [
                'id' => 'entity-id-2',
                'name' => 'Updated Name 2',
            ],
        ];

        $expectedEntity1 = new ProductEntity();
        $expectedEntity1->setId('entity-id-1');
        $expectedEntity1->setName('Updated Name 1');

        $expectedEntity2 = new ProductEntity();
        $expectedEntity2->setId('entity-id-2');
        $expectedEntity2->setName('Updated Name 2');

        $entities = new ProductCollection([$expectedEntity1, $expectedEntity2]);

        $result = $this->createMock(EntitySearchResult::class);
        $result->expects($this->once())
            ->method('getEntities')
            ->willReturn($entities);

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $event->expects($this->once())
            ->method('getPrimaryKeys')
            ->willReturn(['entity-id-1', 'entity-id-2']);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with($data)
            ->willReturn($event);

        $repository->expects($this->once())
            ->method('search')
            ->with(new Criteria(['entity-id-1', 'entity-id-2']))
            ->willReturn($result);

        $context = $this->createMock(Context::class);
        $this->queryBuilder->setContext($context);
        $this->queryBuilder->setRepository($repository);

        $result = $this->queryBuilder->update($data);

        self::assertInstanceOf(EntityCollection::class, $result);
        self::assertEquals($expectedEntity1, $result->first());
        self::assertEquals($expectedEntity2, $result->last());
    }

    public function testUpdateWithConditions(): void
    {
        $data = [
            'name' => 'Updated Name 1',
        ];

        $expectedData = [
            [
                'id' => 'entity-id',
                'name' => 'Updated Name 1',
            ],
        ];

        $expectedEntity = new ProductEntity();
        $expectedEntity->setId('entity-id');
        $expectedEntity->setName('Updated Name 2');

        $result = $this->createMock(EntitySearchResult::class);
        $result->expects($this->once())
            ->method('getEntities')
            ->willReturn(new ProductCollection([$expectedEntity]));

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $event->expects($this->once())
            ->method('getPrimaryKeys')
            ->willReturn(['entity-id']);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with($expectedData)
            ->willReturn($event);

        $repository->expects($this->once())
            ->method('search')
            ->with(new Criteria(['entity-id']))
            ->willReturn($result);

        $idResult = $this->createMock(IdSearchResult::class);
        $idResult->expects($this->once())
            ->method('getIds')
            ->willReturn(['entity-id']);

        $repository->expects($this->once())
            ->method('searchIds')
            ->with((new Criteria())->addFilter(new EqualsFilter('name', 'Product Name')))
            ->willReturn($idResult);

        $this->propertyResolver->expects($this->any())
            ->method('resolve')
            ->with(ProductEntity::class, 'name')
            ->willReturn('name');

        $context = $this->createMock(Context::class);
        $this->queryBuilder->setContext($context);
        $this->queryBuilder->setRepository($repository);

        $result = $this->queryBuilder
            ->where('name = "Product Name"')
            ->update($data)
        ;

        self::assertInstanceOf(ProductEntity::class, $result);
    }

    public function testInsertWithoutRepositoryShouldThrowError(): void
    {
        $context = $this->createMock(Context::class);
        $this->queryBuilder->setContext($context);

        $this->expectException(\RuntimeException::class);
        $this->queryBuilder->insert([[]]);
    }

    public function testInsertShouldReturnEntityInserted(): void
    {
        $entityData = [
            'id' => 'entity-id',
            'name' => 'Updated Name',
        ];

        $expectedEntity = new ProductEntity();
        $expectedEntity->setId('entity-id');
        $expectedEntity->setName('Updated Name');

        $entities = new ProductCollection([$expectedEntity]);

        $result = $this->createMock(EntitySearchResult::class);
        $result->expects($this->once())
            ->method('getEntities')
            ->willReturn($entities);

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $event->expects($this->once())
            ->method('getPrimaryKeys')
            ->willReturn(['entity-id']);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with([$entityData])
            ->willReturn($event)
        ;
        $repository->expects($this->once())
            ->method('search')
            ->with(new Criteria([$entityData['id']]))
            ->willReturn($result);

        $context = $this->createMock(Context::class);
        $this->queryBuilder->setContext($context);
        $this->queryBuilder->setRepository($repository);

        $result = $this->queryBuilder->insert([
            $entityData,
        ]);

        self::assertInstanceOf(ProductEntity::class, $result);
        self::assertSame('entity-id', $result->getId());
        self::assertSame('Updated Name', $result->getName());
    }

    /**
     * @throws UpdateEntityException
     */
    public function testInsertCanUpdateMultipleEntity(): void
    {
        $data = [
            [
                'id' => 'entity-id-1',
                'name' => 'Updated Name 1',
            ],
            [
                'id' => 'entity-id-2',
                'name' => 'Updated Name 2',
            ],
        ];

        $expectedEntity1 = new ProductEntity();
        $expectedEntity1->setId('entity-id-1');
        $expectedEntity1->setName('Updated Name 1');

        $expectedEntity2 = new ProductEntity();
        $expectedEntity2->setId('entity-id-2');
        $expectedEntity2->setName('Updated Name 2');

        $entities = new ProductCollection([$expectedEntity1, $expectedEntity2]);

        $result = $this->createMock(EntitySearchResult::class);
        $result->expects($this->once())
            ->method('getEntities')
            ->willReturn($entities);

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $event->expects($this->once())
            ->method('getPrimaryKeys')
            ->willReturn(['entity-id-1', 'entity-id-2']);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($event);

        $repository->expects($this->once())
            ->method('search')
            ->with(new Criteria(['entity-id-1', 'entity-id-2']))
            ->willReturn($result);

        $context = $this->createMock(Context::class);
        $this->queryBuilder->setContext($context);
        $this->queryBuilder->setRepository($repository);

        $result = $this->queryBuilder->insert($data);

        self::assertInstanceOf(EntityCollection::class, $result);
        self::assertEquals($expectedEntity1, $result->first());
        self::assertEquals($expectedEntity2, $result->last());
    }

    public function testQueryBuilderTitle(): void
    {
        $this->queryBuilder->title('title');

        $title = $this->queryBuilder->getTitle();

        self::assertEquals('title', $title);
    }

    public function testQueryBuilderAnotherTitle(): void
    {
        $this->queryBuilder->title('title 2');

        $title = $this->queryBuilder->getTitle();

        self::assertEquals('title 2', $title);
    }
}
