<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for nested queries and complex OR/AND conditions.
 *
 * Tests complex logical groupings and nested conditions.
 */
#[CoversNothing]
class RealDatabaseNestedQueriesTest extends KernelAwareTestCase
{
    /**
     * Test simple OR condition: (active = true OR stock > 100).
     */
    public function testSimpleOrCondition(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('active = true')
                    ->where('stock > 100');
            })
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $this->assertTrue(
                $product->getActive() || $product->getStock() > 100,
                'Product must be either active OR have stock > 100'
            );
        }
    }

    /**
     * Test nested AND within OR: ((active = true AND stock > 0) OR stock > 100).
     */
    public function testNestedAndWithinOr(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where(function (QueryBuilder $q): void {
                    $q->where('active = true')
                        ->where('stock > 0');
                })
                    ->where('stock > 100');
            })
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $condition1 = $product->getActive() && $product->getStock() > 0;
            $condition2 = $product->getStock() > 100;

            $this->assertTrue(
                $condition1 || $condition2,
                'Product must match: (active AND stock > 0) OR stock > 100'
            );
        }
    }

    /**
     * Test multiple OR conditions: (stock < 10 OR stock > 100 OR active = false).
     */
    public function testMultipleOrConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('stock < 10')
                    ->where('stock > 100')
                    ->where('active = false');
            })
            ->get();

        foreach ($result as $product) {
            $this->assertTrue(
                $product->getStock() < 10 || $product->getStock() > 100 || ! $product->getActive(),
                'Product must match at least one of the OR conditions'
            );
        }
    }

    /**
     * Test complex nested: ((active = true AND stock > 50) OR (active = false AND stock < 10)).
     */
    public function testComplexNestedOrGroups(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where(function (QueryBuilder $q): void {
                    $q->where('active = true')
                        ->where('stock > 50');
                })
                    ->where(function (QueryBuilder $q): void {
                        $q->where('active = false')
                            ->where('stock < 10');
                    });
            })
            ->get();

        foreach ($result as $product) {
            $condition1 = $product->getActive() && $product->getStock() > 50;
            $condition2 = ! $product->getActive() && $product->getStock() < 10;

            $this->assertTrue(
                $condition1 || $condition2,
                'Product must match one of the complex conditions'
            );
        }
    }

    /**
     * Test deeply nested conditions: three levels deep.
     */
    public function testDeeplyNestedConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where(function (QueryBuilder $query): void {
                $query->orWhere(function (QueryBuilder $q1): void {
                    $q1->where(function (QueryBuilder $q2): void {
                        $q2->where('active = true')
                            ->where('stock > 0');
                    })
                        ->where('stock > 200');
                })
                    ->where('productNumber IS NOT NULL');
            })
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);

        foreach ($result as $product) {
            // All must have non-null product number
            $this->assertNotNull($product->getProductNumber());
        }
    }

    /**
     * Test OR with BETWEEN condition.
     */
    public function testOrWithBetweenCondition(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock >= 0 AND stock <= 50 OR active = false')
            ->get();

        foreach ($result as $product) {
            $inRange = $product->getStock() >= 0 && $product->getStock() <= 50;
            $inactive = ! $product->getActive();

            $this->assertTrue(
                $inRange || $inactive,
                'Product must have stock between 0-50 OR be inactive'
            );
        }
    }

    /**
     * Test OR with IN condition.
     */
    public function testOrWithInCondition(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $result = $queryBuilder->limit(3)->get();

        if ($result->count() < 3) {
            $this->markTestSkipped('Not enough products for IN test');
        }

        $ids = array_slice($result->getIds(), 0, 2);

        $queryBuilder2 = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result2 */
        $result2 = $queryBuilder2
            ->orWhere(function (QueryBuilder $query) use ($ids): void {
                $query->whereIn('id', $ids)
                    ->where('stock > 500');
            })
            ->get();

        foreach ($result2 as $product) {
            $this->assertTrue(
                in_array($product->getId(), $ids) || $product->getStock() > 500,
                'Product must be in ID list OR have stock > 500'
            );
        }
    }

    /**
     * Test combining top-level AND with nested OR.
     */
    public function testTopLevelAndWithNestedOr(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('stock > 0')
                    ->where('stock IS NULL');
            })
            ->get();

        foreach ($result as $product) {
            // All must be active
            $this->assertTrue($product->getActive());

            // AND (stock > 0 OR stock IS NULL) - though stock likely never null
            $this->assertTrue(
                $product->getStock() > 0 || $product->getStock() === null
            );
        }
    }

    /**
     * Test NOT NULL within OR group.
     */
    public function testNotNullWithinOrGroup(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->whereNotNull('manufacturerId')
                    ->where('stock > 150');
            })
            ->get();

        foreach ($result as $product) {
            $this->assertTrue(
                $product->getManufacturerId() !== null || $product->getStock() > 150,
                'Product must have manufacturer OR stock > 150'
            );
        }
    }

    /**
     * Test complex filter with LIKE within nested OR.
     */
    public function testLikeWithinNestedOr(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where(function (QueryBuilder $query): void {
                $query->where('name LIKE "%shoe%"')
                    ->orWhere('stock < 5');
            })
            ->get();

        foreach ($result as $product) {
            // Must be active
            $this->assertTrue($product->getActive());

            // AND (name contains shoe OR stock < 5)
            $hasShoe = stripos($product->getName() ?? '', 'shoe') !== false;
            $lowStock = $product->getStock() < 5;

            $this->assertTrue($hasShoe || $lowStock);
        }
    }

    /**
     * Test combining multiple where calls with OR groups.
     */
    public function testMixedWhereAndOrGroups(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('productNumber IS NOT NULL')
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('active = true')
                    ->where('stock = 0');
            })
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('stock >= 0')
                    ->where('stock IS NULL');
            })
            ->get();

        foreach ($result as $product) {
            $this->assertNotNull($product->getProductNumber());
            $this->assertTrue($product->getActive() || $product->getStock() === 0);
        }
    }

    /**
     * Test alternating OR conditions with AND conditions.
     */
    public function testAlternatingOrAndConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where(function (QueryBuilder $query): void {
                $query->where('stock > 0')
                    ->where('active = true');
            })
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('stock = 0')
                    ->where('active = false');
            })
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);

        foreach ($result as $product) {
            $condition1 = $product->getStock() > 0 && $product->getActive();
            $condition2 = $product->getStock() === 0 && ! $product->getActive();

            $this->assertTrue(
                $condition1 || $condition2,
                'Product must match either group'
            );
        }
    }
}
