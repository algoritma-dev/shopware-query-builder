<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for complex filtering scenarios.
 *
 * Tests edge cases, combinations of operators, and advanced filter patterns.
 */
#[CoversNothing]
class RealDatabaseComplexFilteringTest extends KernelAwareTestCase
{
    /**
     * Test combining LIKE with BETWEEN.
     */
    public function testLikeWithBetween(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('productNumber LIKE "SW-%"')
            ->whereBetween('stock', 0, 100)
            ->get();

        foreach ($result as $product) {
            $this->assertStringStartsWith('SW-', $product->getProductNumber());
            $this->assertGreaterThanOrEqual(0, $product->getStock());
            $this->assertLessThanOrEqual(100, $product->getStock());
        }
    }

    /**
     * Test combining NOT NULL with IN operator.
     */
    public function testNotNullWithIn(): void
    {
        $queryBuilder = \sw_query(ProductEntity::class);
        $sample = $queryBuilder->limit(5)->get();

        if ($sample->count() < 3) {
            $this->markTestSkipped('Not enough products');
        }

        $ids = array_slice($sample->getIds(), 0, 3);

        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result = $queryBuilder2
            ->whereIn('id', $ids)
            ->whereNotNull('manufacturerId')
            ->get();

        $this->assertLessThanOrEqual(3, $result->count());

        foreach ($result as $product) {
            $this->assertContains($product->getId(), $ids);
            $this->assertNotNull($product->getManufacturerId());
        }
    }

    /**
     * Test multiple LIKE conditions with OR.
     */
    public function testMultipleLikeWithOr(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('name LIKE "%shoe%"')
                    ->orWhere('name LIKE "%boot%"')
                    ->orWhere('name LIKE "%sneaker%"');
            })
            ->get();

        foreach ($result as $product) {
            $name = strtolower($product->getName() ?? '');
            $this->assertTrue(
                str_contains($name, 'shoe')
                || str_contains($name, 'boot')
                || str_contains($name, 'sneaker')
            );
        }
    }

    /**
     * Test complex combination: (A AND B) OR (C AND D).
     */
    public function testComplexAndOrCombination(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where(function (QueryBuilder $query): void {
                $query->where('active = true')
                    ->where('stock > 50');
            })
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('active = false')
                    ->where('stock = 0');
            })
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);

        foreach ($result as $product) {
            $condition1 = $product->getActive() && $product->getStock() > 50;
            $condition2 = ! $product->getActive() && $product->getStock() === 0;

            $this->assertTrue($condition1 || $condition2);
        }
    }

    /**
     * Test NOT IN operator.
     */
    public function testNotInOperator(): void
    {
        $queryBuilder = \sw_query(ProductEntity::class);
        $sample = $queryBuilder->limit(3)->get();

        if ($sample->count() < 2) {
            $this->markTestSkipped('Not enough products');
        }

        $excludeIds = array_slice($sample->getIds(), 0, 2);

        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result = $queryBuilder2
            ->whereNotIn('id', $excludeIds)
            ->limit(5)
            ->get();

        foreach ($result as $product) {
            $this->assertNotContains($product->getId(), $excludeIds);
        }
    }

    /**
     * Test NULL and NOT NULL in same query with OR.
     */
    public function testNullAndNotNullWithOr(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->whereNull('manufacturerId')
                    ->orWhere('stock = 0');
            })
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);

        foreach ($result as $product) {
            $this->assertTrue(
                $product->getManufacturerId() === null || $product->getStock() === 0
            );
        }
    }

    /**
     * Test greater than, less than combined with equals.
     */
    public function testMixedComparisonOperators(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('stock < 5')
                    ->orWhere('stock > 100')
                    ->orWhere('stock = 50');
            })
            ->get();

        foreach ($result as $product) {
            $stock = $product->getStock();
            $this->assertTrue(
                $stock < 5 || $stock > 100 || $stock === 50
            );
        }
    }

    /**
     * Test LIKE with wildcards at different positions.
     */
    public function testLikeWithVariousWildcards(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('productNumber LIKE "SW-%"')
            ->limit(10)
            ->get();

        foreach ($result as $product) {
            $this->assertStringStartsWith('SW-', $product->getProductNumber());
        }

        // Test ends with
        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result2 = $queryBuilder2
            ->where('productNumber LIKE "%-001"')
            ->get();

        foreach ($result2 as $product) {
            $this->assertStringEndsWith('-001', $product->getProductNumber());
        }
    }

    /**
     * Test combining multiple BETWEEN conditions.
     */
    public function testMultipleBetweenConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->whereBetween('stock', 0, 50)
                    ->orWhere(function (QueryBuilder $q): void {
                        $q->whereBetween('stock', 100, 200);
                    });
            })
            ->get();

        foreach ($result as $product) {
            $stock = $product->getStock();
            $inRange1 = $stock >= 0 && $stock <= 50;
            $inRange2 = $stock >= 100 && $stock <= 200;

            $this->assertTrue($inRange1 || $inRange2);
        }
    }

    /**
     * Test negation with NOT equals.
     */
    public function testNotEqualsOperator(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active != false')
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
        }
    }

    /**
     * Test complex filter with aggregation.
     */
    public function testComplexFilterWithAggregation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('active = true')
                    ->orWhere(function (QueryBuilder $q): void {
                        $q->whereBetween('stock', 10, 100)
                            ->orWhere('stock > 200');
                    });
            })
            ->addCount('id', 'filtered_count')
            ->addSum('stock', 'filtered_sum')
            ->get();

        $countResult = $result->getAggregations()->get('filtered_count');
        $sumResult = $result->getAggregations()->get('filtered_sum');

        $this->assertGreaterThanOrEqual(0, $countResult->getCount());
        $this->assertGreaterThanOrEqual(0, $sumResult->getSum());
    }

    /**
     * Test filter with sorting and pagination.
     */
    public function testComplexFilterWithSortingAndPagination(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('active = true')
                    ->where('stock > 0');
            })
            ->orderBy('stock', 'DESC')
            ->orderBy('productNumber', 'ASC')
            ->limit(5)
            ->offset(0)
            ->get();

        $this->assertLessThanOrEqual(5, $result->count());

        // Verify sorting
        if ($result->count() > 1) {
            $stocks = array_values(array_map(fn (Entity $p) => $p->getStock(), $result->getElements()));
            $sortedStocks = $stocks;
            rsort($sortedStocks);
            $this->assertEquals($sortedStocks, $stocks);
        }
    }

    /**
     * Test multiple NOT conditions.
     */
    public function testMultipleNotConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active != false')
            ->whereNotNull('manufacturerId')
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertNotNull($product->getManufacturerId());
        }
    }

    /**
     * Test filter chain with all major operators.
     */
    public function testFilterChainWithAllOperators(): void
    {
        $queryBuilder = \sw_query(ProductEntity::class);
        $sample = $queryBuilder->limit(2)->get();

        if ($sample->count() < 2) {
            $this->markTestSkipped('Not enough products');
        }

        $ids = array_slice($sample->getIds(), 0, 2);

        /** @var QueryBuilder $queryBuilder2 */
        $queryBuilder2 = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder2
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->whereIn('id', $ids)
            ->where('productNumber LIKE "SW-%"')
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
            $this->assertNotNull($product->getManufacturerId());
            $this->assertContains($product->getId(), $ids);
            $this->assertStringStartsWith('SW-', $product->getProductNumber());
        }
    }

    /**
     * Test deeply nested OR with AND conditions.
     */
    public function testDeeplyNestedOrAndConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->orWhere(function (QueryBuilder $q1): void {
                    $q1->orWhere(function (QueryBuilder $q2): void {
                        $q2->where('active = true')
                            ->where('stock > 0');
                    })
                        ->orWhere(function (QueryBuilder $q2): void {
                            $q2->where('active = false')
                                ->where('stock = 0');
                        });
                })
                    ->orWhere(function (QueryBuilder $q1): void {
                        $q1->where('stock > 100')
                            ->whereNotNull('manufacturerId');
                    });
            })
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);
    }

    /**
     * Test LIKE case insensitive search.
     */
    public function testLikeCaseInsensitive(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('name LIKE "%SHOE%"')
            ->limit(5)
            ->get();

        foreach ($result as $product) {
            $this->assertStringContainsStringIgnoringCase('shoe', $product->getName() ?? '');
        }
    }

    /**
     * Test empty result set handling.
     */
    public function testEmptyResultSetHandling(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock < 0')
            ->get();

        $this->assertEquals(0, $result->count());
        $this->assertInstanceOf(EntitySearchResult::class, $result);
    }

    /**
     * Test filter with associations and complex conditions.
     */
    public function testFilterWithAssociationsAndComplexConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('active = true')
                    ->orWhere(function (QueryBuilder $q): void {
                        $q->whereNotNull('manufacturerId')
                            ->orWhere('stock > 100');
                    });
            })
            ->with('manufacturer')
            ->with('categories')
            ->orderBy('productNumber', 'ASC')
            ->limit(10)
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertTrue(
                $product->getManufacturerId() !== null || $product->getStock() > 100
            );
        }
    }

    /**
     * Test combining all operators in single complex query.
     */
    public function testAllOperatorsCombined(): void
    {
        $queryBuilder = \sw_query(ProductEntity::class);
        $sample = $queryBuilder->limit(3)->get();

        if ($sample->count() < 2) {
            $this->markTestSkipped('Not enough products');
        }

        $ids = array_slice($sample->getIds(), 0, 2);

        /** @var QueryBuilder $queryBuilder2 */
        $queryBuilder2 = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder2
            ->orWhere(function (QueryBuilder $query) use ($ids): void {
                $query->orWhere(function (QueryBuilder $q): void {
                    $q->where('active = true')
                        ->whereBetween('stock', 0, 200)
                        ->whereNotNull('manufacturerId');
                })
                    ->orWhere(function (QueryBuilder $q) use ($ids): void {
                        $q->whereIn('id', $ids)
                            ->where('stock > 50');
                    });
            })
            ->with('manufacturer')
            ->orderBy('stock', 'DESC')
            ->addCount('id', 'total_count')
            ->addSum('stock', 'total_stock')
            ->limit(10)
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);
        $this->assertNotNull($result->getAggregations()->get('total_count'));
        $this->assertNotNull($result->getAggregations()->get('total_stock'));
    }
}
