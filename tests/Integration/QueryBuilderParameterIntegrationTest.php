<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidParameterException;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

#[CoversNothing]
class QueryBuilderParameterIntegrationTest extends TestCase
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

    public function testWhereWithSingleParameter(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active')
            ->setParameter('active', true);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(EqualsFilter::class, $filters[0]);
        $this->assertSame('active', $filters[0]->getField());
        $this->assertTrue($filters[0]->getValue());
    }

    public function testWhereWithMultipleParameters(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active')
            ->where('stock > :minStock')
            ->setParameters([
                'active' => true,
                'minStock' => 10,
            ]);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(MultiFilter::class, $filters[0]);

        $nestedFilters = $filters[0]->getQueries();
        $this->assertCount(2, $nestedFilters);
    }

    public function testWhereInWithParameter(): void
    {
        $statuses = ['active', 'inactive', 'pending'];

        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('status IN (:statuses)')
            ->setParameter('statuses', $statuses);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(EqualsAnyFilter::class, $filters[0]);
        $this->assertSame($statuses, $filters[0]->getValue());
    }

    public function testWhereBetweenWithParameters(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('price >= :minPrice')
            ->where('price <= :maxPrice')
            ->setParameter('minPrice', 100)
            ->setParameter('maxPrice', 500);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(MultiFilter::class, $filters[0]);
    }

    public function testCompoundExpressionWithParameters(): void
    {
        $this->definitionResolver->method('hasField')->willReturn(true);

        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active AND stock > :minStock')
            ->setParameters([
                'active' => true,
                'minStock' => 10,
            ]);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(MultiFilter::class, $filters[0]);
    }

    public function testOrWhereWithParameters(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active')
            ->orWhere('featured = :featured')
            ->setParameter('active', true)
            ->setParameter('featured', true);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(2, $filters);
    }

    public function testParameterReplacementInNestedGroups(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where(function ($q): void {
                $q->where('status = :status')
                    ->where('stock > :minStock');
            })
            ->setParameters([
                'status' => 'active',
                'minStock' => 10,
            ]);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(MultiFilter::class, $filters[0]);
    }

    public function testMissingParameterThrowsException(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active');

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage("Parameter ':active' is not set");

        $queryBuilder->toCriteria();
    }

    public function testParameterOverwrite(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active')
            ->setParameter('active', false)
            ->setParameter('active', true);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertInstanceOf(EqualsFilter::class, $filters[0]);
        $this->assertTrue($filters[0]->getValue());
    }

    public function testFluentChaining(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active')
            ->setParameter('active', true)
            ->where('stock > :minStock')
            ->setParameter('minStock', 10)
            ->where('price <= :maxPrice')
            ->setParameter('maxPrice', 500);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(MultiFilter::class, $filters[0]);

        $nestedFilters = $filters[0]->getQueries();
        $this->assertCount(3, $nestedFilters);
    }

    public function testParametersWithDifferentTypes(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active')
            ->where('stock > :minStock')
            ->where('price >= :minPrice')
            ->where('name = :name')
            ->setParameters([
                'active' => true,
                'minStock' => 10,
                'minPrice' => 99.99,
                'name' => 'Product',
            ]);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(MultiFilter::class, $filters[0]);
    }

    public function testLikeWithParameter(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('name LIKE :searchTerm')
            ->setParameter('searchTerm', '%product%');

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
    }

    public function testParameterNameNormalization(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active')
            ->setParameter(':active', true);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(EqualsFilter::class, $filters[0]);
        $this->assertTrue($filters[0]->getValue());
    }

    public function testComplexQueryWithMultipleParameterTypes(): void
    {
        $this->definitionResolver->method('hasField')->willReturn(true);

        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('active = :active')
            ->where('stock > :minStock AND stock < :maxStock')
            ->where('status IN (:statuses)')
            ->orWhere('featured = :featured')
            ->setParameters([
                'active' => true,
                'minStock' => 10,
                'maxStock' => 100,
                'statuses' => ['available', 'pending'],
                'featured' => true,
            ]);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertGreaterThan(0, count($filters));
    }

    public function testParametersInAliasedQueries(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class, 'p')
            ->where('p.active = :active')
            ->where('p.stock > :minStock')
            ->setParameter('active', true)
            ->setParameter('minStock', 10);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(MultiFilter::class, $filters[0]);
    }

    public function testSetParametersDoesNotAffectExistingParameters(): void
    {
        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->setParameter('existing', 'value')
            ->setParameters([
                'active' => true,
                'stock' => 10,
            ]);

        $parameters = $queryBuilder->getParameters();

        $this->assertTrue($parameters->has('existing'));
        $this->assertTrue($parameters->has('active'));
        $this->assertTrue($parameters->has('stock'));
        $this->assertSame(3, $parameters->count());
    }

    public function testNullParameterValue(): void
    {
        $this->definitionResolver->method('hasField')->willReturn(false); // deletedAt field does not exist to skip soft delete logic

        $queryBuilder = $this->createQueryBuilder(ProductEntity::class)
            ->where('customField = :customField')
            ->setParameter('customField', null);

        $criteria = $queryBuilder->toCriteria();
        $filters = $criteria->getFilters();

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(EqualsFilter::class, $filters[0]);
        $this->assertNull($filters[0]->getValue());
    }

    private function createQueryBuilder(string $entityClass, ?string $alias = null): QueryBuilder
    {
        $queryBuilder = new QueryBuilder(
            $entityClass,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            new RawExpressionParser()
        );

        if ($alias !== null) {
            $queryBuilder->setAlias($alias);
        }

        return $queryBuilder;
    }
}
