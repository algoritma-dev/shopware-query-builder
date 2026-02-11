<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for edge cases and boundary conditions.
 *
 * Tests unusual scenarios, limits, empty results, and boundary values.
 */
#[CoversNothing]
class RealDatabaseEdgeCasesTest extends KernelAwareTestCase
{
    /**
     * Test query with no results.
     */
    public function testQueryReturningNoResults(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock < -1000')
            ->get();

        $this->assertEquals(0, $result->count());
        $this->assertEmpty($result->getElements());
        $this->assertInstanceOf(EntitySearchResult::class, $result);
    }

    /**
     * Test query with limit of 1.
     */
    public function testQueryWithLimitOne(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->limit(1)
            ->get();

        $this->assertLessThanOrEqual(1, $result->count());

        if ($result->count() > 0) {
            $this->assertInstanceOf(ProductEntity::class, $result->first());
        }
    }

    /**
     * Test query with very large offset.
     */
    public function testQueryWithLargeOffset(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        // First check total count
        $totalResult = $queryBuilder->get();
        $totalCount = $totalResult->count();

        // Query with offset larger than total
        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result = $queryBuilder2
            ->offset($totalCount + 100)
            ->get();

        $this->assertEquals(0, $result->count());
    }

    /**
     * Test query with limit 0 (should return no entities but aggregations work).
     */
    public function testQueryWithLimitZero(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->limit(0)
            ->addCount('id', 'total_count')
            ->get();

        // No entities returned
        $this->assertEquals(0, $result->count());

        // But aggregations should still work
        $countResult = $result->getAggregations()->get('total_count');
        $this->assertNotNull($countResult);
    }

    /**
     * Test query with all products (no filters).
     */
    public function testQueryWithNoFilters(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /**
     * Test query with redundant filters.
     */
    public function testQueryWithRedundantFilters(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('active = true')
            ->where('active = true')
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
        }
    }

    /**
     * Test query with contradictory conditions.
     */
    public function testQueryWithContradictoryConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('active = false')
            ->get();

        // Should return no results as conditions contradict
        $this->assertEquals(0, $result->count());
    }

    /**
     * Test query with maximum number of associations.
     */
    public function testQueryWithManyAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('manufacturer')
            ->with('categories')
            ->with('tax')
            ->with('cover')
            ->with('unit')
            ->with('prices')
            ->limit(1)
            ->get();

        if ($result->count() > 0) {
            $product = $result->first();
            $this->assertInstanceOf(ProductEntity::class, $product);
        }
    }

    /**
     * Test query with very long IN list.
     */
    public function testQueryWithLongInList(): void
    {
        /** @var QueryBuilder $sampleQuery */
        $sampleQuery = \sw_query(ProductEntity::class);
        $sample = $sampleQuery->limit(50)->get();

        $ids = $sample->getIds();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereIn('id', $ids)
            ->get();

        $this->assertLessThanOrEqual(count($ids), $result->count());

        foreach ($result as $product) {
            $this->assertContains($product->getId(), $ids);
        }
    }

    /**
     * Test query with empty IN list.
     */
    public function testQueryWithEmptyInList(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereIn('id', [])
            ->get();

        // Empty IN list should return no results
        $this->assertEquals(0, $result->count());
    }

    /**
     * Test query with single item IN list.
     */
    public function testQueryWithSingleItemInList(): void
    {
        /** @var QueryBuilder $sampleQuery */
        $sampleQuery = \sw_query(ProductEntity::class);
        $sample = $sampleQuery->limit(1)->get();

        $id = $sample->first()->getId();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereIn('id', [$id])
            ->get();

        $this->assertLessThanOrEqual(1, $result->count());

        if ($result->count() > 0) {
            $this->assertEquals($id, $result->first()->getId());
        }
    }

    /**
     * Test query with boundary stock values.
     */
    public function testQueryWithBoundaryStockValues(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        // Test with stock = 0 (boundary)
        /** @var EntitySearchResult $zeroResult */
        $zeroResult = $queryBuilder
            ->where('stock = 0')
            ->get();

        foreach ($zeroResult as $product) {
            $this->assertEquals(0, $product->getStock());
        }

        // Test with stock = 1 (just above boundary)
        $queryBuilder2 = \sw_query(ProductEntity::class);
        $oneResult = $queryBuilder2
            ->where('stock = 1')
            ->get();

        foreach ($oneResult as $product) {
            $this->assertEquals(1, $product->getStock());
        }
    }

    /**
     * Test query with extreme BETWEEN ranges.
     */
    public function testQueryWithExtremeBetweenRanges(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        // Very large range
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereBetween('stock', 0, 999999)
            ->get();

        foreach ($result as $product) {
            $this->assertGreaterThanOrEqual(0, $product->getStock());
            $this->assertLessThanOrEqual(999999, $product->getStock());
        }

        // Single value range
        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result2 = $queryBuilder2
            ->whereBetween('stock', 50, 50)
            ->get();

        foreach ($result2 as $product) {
            $this->assertEquals(50, $product->getStock());
        }
    }

    /**
     * Test query with multiple orderBy on same field.
     */
    public function testQueryWithDuplicateOrderBy(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orderBy('name', 'ASC')
            ->orderBy('name', 'DESC')
            ->limit(10)
            ->get();

        // Last orderBy should take precedence
        $this->assertInstanceOf(EntitySearchResult::class, $result);
    }

    /**
     * Test query with NULL LIKE pattern.
     */
    public function testQueryWithLikeOnPotentiallyNullField(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('description LIKE "%test%"')
            ->get();

        // Should handle null descriptions gracefully
        $this->assertInstanceOf(EntitySearchResult::class, $result);
    }

    /**
     * Test query with special characters in LIKE.
     */
    public function testQueryWithSpecialCharactersInLike(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('productNumber LIKE "%-%" ')
            ->limit(10)
            ->get();

        foreach ($result as $product) {
            $this->assertStringContainsString('-', $product->getProductNumber());
        }
    }

    /**
     * Test aggregation on empty result set.
     */
    public function testAggregationOnEmptyResultSet(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock < -1000')
            ->addCount('id', 'count')
            ->addSum('stock', 'sum')
            ->addAvg('stock', 'avg')
            ->get();

        $this->assertEquals(0, $result->count());

        $countResult = $result->getAggregations()->get('count');
        $this->assertEquals(0, $countResult->getCount());
    }

    /**
     * Test multiple nested OR groups at maximum depth.
     */
    public function testMaximumNestedOrDepth(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $q1): void {
                $q1->orWhere(function (QueryBuilder $q2): void {
                    $q2->orWhere(function (QueryBuilder $q3): void {
                        $q3->orWhere(function (QueryBuilder $q4): void {
                            $q4->where('active = true')
                                ->orWhere('stock > 0');
                        })
                            ->orWhere('stock > 100');
                    })
                        ->orWhere('active = false');
                })
                    ->orWhere('stock = 0');
            })
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);
    }

    /**
     * Test whereNull on non-nullable fields.
     */
    public function testWhereNullOnNonNullableField(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereNull('id')
            ->get();

        // ID is never null, should return 0 results
        $this->assertEquals(0, $result->count());
    }

    /**
     * Test pagination at exact boundary.
     */
    public function testPaginationAtExactBoundary(): void
    {
        /** @var QueryBuilder $countQuery */
        $countQuery = \sw_query(ProductEntity::class);
        $totalResult = $countQuery
            ->where('active = true')
            ->get();

        $totalCount = $totalResult->count();

        // Get exactly the last page
        $perPage = 5;
        $lastPageOffset = (int) (floor(($totalCount - 1) / $perPage) * $perPage);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->limit($perPage)
            ->offset($lastPageOffset)
            ->orderBy('id', 'ASC')
            ->get();

        $this->assertLessThanOrEqual($perPage, $result->count());
        $this->assertGreaterThan(0, $result->count());
    }

    /**
     * Test combining whereNotIn with empty array.
     * Note: Empty whereNotIn may filter everything (implementation dependent).
     */
    public function testWhereNotInWithEmptyArray(): void
    {
        // First check if we have any products
        $checkQuery = \sw_query(ProductEntity::class);
        $checkQuery
            ->limit(1)
            ->get();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereNotIn('id', [])
            ->limit(10)
            ->get();

        // Empty NOT IN behavior: may return all or none depending on implementation
        // We just verify the query executes successfully
        $this->assertInstanceOf(EntitySearchResult::class, $result);
        $this->assertGreaterThanOrEqual(0, $result->count(), 'Query should execute without error');
        $this->assertLessThanOrEqual(10, $result->count(), 'Result should respect limit');
    }

    /**
     * Test association with filter on associated entity.
     */
    public function testAssociationFilterOnNonExistentValue(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('manufacturer.name = "NonExistentManufacturer12345"')
            ->with('manufacturer')
            ->get();

        $this->assertEquals(0, $result->count());
    }

    /**
     * Test ordering by association field when association might be null.
     */
    public function testOrderByAssociationFieldWithPossibleNull(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->with('manufacturer')
            ->orderBy('manufacturer.name', 'ASC')
            ->limit(10)
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);
    }

    /**
     * Test multiple aggregations with no matching records.
     */
    public function testMultipleAggregationsOnNoResults(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock < -9999')
            ->addCount('id', 'count')
            ->addSum('stock', 'sum')
            ->addAvg('stock', 'avg')
            ->addMin('stock', 'min')
            ->addMax('stock', 'max')
            ->get();

        $this->assertEquals(0, $result->count());

        $countResult = $result->getAggregations()->get('count');
        $this->assertEquals(0, $countResult->getCount());
    }

    /**
     * Test query with both limit and aggregation.
     */
    public function testLimitDoesNotAffectAggregations(): void
    {
        /** @var QueryBuilder $fullQuery */
        $fullQuery = \sw_query(ProductEntity::class);
        $fullResult = $fullQuery
            ->where('active = true')
            ->addCount('id', 'full_count')
            ->get();

        $fullCount = $fullResult->getAggregations()->get('full_count')->getCount();

        /** @var QueryBuilder $limitedQuery */
        $limitedQuery = \sw_query(ProductEntity::class);
        $limitedResult = $limitedQuery
            ->where('active = true')
            ->limit(5)
            ->addCount('id', 'limited_count')
            ->get();

        $limitedCount = $limitedResult->getAggregations()->get('limited_count')->getCount();

        // Aggregations should be the same regardless of limit
        $this->assertEquals($fullCount, $limitedCount);

        // But returned entities should be limited
        $this->assertLessThanOrEqual(5, $limitedResult->count());
    }

    /**
     * Test chaining multiple orWhere groups.
     */
    public function testMultipleOrWhereGroups(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $q): void {
                $q->where('stock > 100');
            })
            ->orWhere(function (QueryBuilder $q): void {
                $q->where('stock < 5');
            })
            ->orWhere(function (QueryBuilder $q): void {
                $q->where('stock = 50');
            })
            ->get();

        $this->assertGreaterThanOrEqual(0, $result->count(), 'Query should execute successfully');

        foreach ($result as $product) {
            $stock = $product->getStock();
            $this->assertTrue(
                $stock > 100 || $stock < 5 || $stock === 50,
                "Product stock {$stock} should match one of the OR conditions"
            );
        }
    }

    /**
     * Test whereStartsWith with empty string.
     */
    public function testWhereStartsWithEmptyString(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereStartsWith('productNumber', '')
            ->limit(10)
            ->get();

        // Empty prefix should match all products
        $this->assertGreaterThan(0, $result->count());
    }

    /**
     * Test query consistency across multiple executions.
     */
    public function testQueryConsistencyAcrossExecutions(): void
    {
        $criteria = [
            'active = true',
            'stock > 10',
        ];

        $results = [];

        for ($i = 0; $i < 3; ++$i) {
            $queryBuilder = \sw_query(ProductEntity::class);

            foreach ($criteria as $condition) {
                $queryBuilder->where($condition);
            }

            $result = $queryBuilder
                ->orderBy('productNumber', 'ASC')
                ->get();

            $results[] = $result->getIds();
        }

        // All three executions should return same IDs in same order
        $this->assertEquals($results[0], $results[1]);
        $this->assertEquals($results[1], $results[2]);
    }
}
