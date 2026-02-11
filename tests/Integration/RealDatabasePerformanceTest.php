<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for performance and optimization scenarios.
 *
 * Tests query performance, caching, and optimization patterns.
 */
#[CoversNothing]
class RealDatabasePerformanceTest extends KernelAwareTestCase
{
    /**
     * Test query performance with large result sets.
     */
    public function testLargeResultSetQuery(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->limit(100)
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertLessThanOrEqual(100, $result->count());

        // Query should execute in reasonable time (less than 2 seconds)
        $this->assertLessThan(2.0, $executionTime, 'Query took too long to execute');
    }

    /**
     * Test query with many associations loaded.
     */
    public function testQueryWithManyAssociationsPerformance(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('manufacturer')
            ->with('categories')
            ->with('tax')
            ->with('unit')
            ->with('cover')
            ->with('prices')
            ->limit(20)
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertGreaterThan(0, $result->count());

        // Loading multiple associations should still be performant
        $this->assertLessThan(3.0, $executionTime, 'Query with associations took too long');
    }

    /**
     * Test aggregation performance.
     */
    public function testAggregationPerformance(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->addCount('id', 'count')
            ->addSum('stock', 'sum')
            ->addAvg('stock', 'avg')
            ->addMin('stock', 'min')
            ->addMax('stock', 'max')
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $aggregations = $result->getAggregations();
        $this->assertNotNull($aggregations->get('count'));
        $this->assertNotNull($aggregations->get('sum'));
        $this->assertNotNull($aggregations->get('avg'));
        $this->assertNotNull($aggregations->get('min'));
        $this->assertNotNull($aggregations->get('max'));

        // Aggregations should be fast
        $this->assertLessThan(2.0, $executionTime, 'Aggregations took too long');
    }

    /**
     * Test complex nested query performance.
     */
    public function testComplexNestedQueryPerformance(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where(function (QueryBuilder $q): void {
                    $q->where('active = true')
                        ->where('stock > 0');
                })
                    ->orWhere(function (QueryBuilder $q): void {
                        $q->where('active = false')
                            ->where('stock > 100');
                    });
            })
            ->with('manufacturer')
            ->orderBy('stock', 'DESC')
            ->limit(50)
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertInstanceOf(EntitySearchResult::class, $result);

        // Complex nested queries should still execute quickly
        $this->assertLessThan(2.0, $executionTime, 'Complex nested query took too long');
    }

    /**
     * Test pagination performance across pages.
     */
    public function testPaginationPerformance(): void
    {
        $executionTimes = [];

        // Test first 5 pages
        for ($page = 0; $page < 5; ++$page) {
            $queryBuilder = \sw_query(ProductEntity::class);

            $startTime = microtime(true);

            $result = $queryBuilder
                ->where('active = true')
                ->orderBy('productNumber', 'ASC')
                ->limit(10)
                ->offset($page * 10)
                ->get();

            $endTime = microtime(true);
            $executionTimes[] = $endTime - $startTime;

            $this->assertInstanceOf(EntitySearchResult::class, $result);
        }

        // All pages should execute in similar time
        $avgTime = array_sum($executionTimes) / count($executionTimes);
        $this->assertLessThan(1.0, $avgTime, 'Average pagination time too high');

        // No single page should take significantly longer
        foreach ($executionTimes as $time) {
            $this->assertLessThan(2.0, $time, 'Individual page took too long');
        }
    }

    /**
     * Test query with IN clause containing many items.
     */
    public function testInClauseWithManyItemsPerformance(): void
    {
        $sampleQuery = \sw_query(ProductEntity::class);
        $sample = $sampleQuery->limit(100)->get();

        $ids = $sample->getIds();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereIn('id', $ids)
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertGreaterThan(0, $result->count());

        // IN clause with many items should still be performant
        $this->assertLessThan(1.5, $executionTime, 'IN clause query took too long');
    }

    /**
     * Test LIKE query performance.
     */
    public function testLikeQueryPerformance(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('name LIKE "%shoe%"')
            ->limit(50)
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertInstanceOf(EntitySearchResult::class, $result);

        // LIKE queries should execute reasonably fast
        $this->assertLessThan(2.0, $executionTime, 'LIKE query took too long');
    }

    /**
     * Test sorting performance on multiple fields.
     */
    public function testMultipleSortingPerformance(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->orderBy('stock', 'DESC')
            ->orderBy('name', 'ASC')
            ->orderBy('productNumber', 'ASC')
            ->limit(100)
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertGreaterThan(0, $result->count());

        // Multiple sorts should not significantly impact performance
        $this->assertLessThan(2.0, $executionTime, 'Multiple sorting took too long');
    }

    /**
     * Test query builder object creation overhead.
     */
    public function testQueryBuilderCreationOverhead(): void
    {
        $startTime = microtime(true);

        // Create multiple query builders
        for ($i = 0; $i < 100; ++$i) {
            $queryBuilder = \sw_query(ProductEntity::class);
            $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Creating query builders should be very fast
        $this->assertLessThan(0.5, $executionTime, 'Query builder creation took too long');
    }

    /**
     * Test memory usage with large result sets.
     */
    public function testMemoryUsageWithLargeResults(): void
    {
        $memoryBefore = memory_get_usage();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->with('manufacturer')
            ->with('categories')
            ->limit(100)
            ->get();

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        $this->assertGreaterThan(0, $result->count());

        // Memory usage should be reasonable (less than 50MB for 100 products)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage too high');
    }

    /**
     * Test query execution consistency.
     */
    public function testQueryExecutionConsistency(): void
    {
        $executionTimes = [];

        // Execute same query 5 times
        for ($i = 0; $i < 5; ++$i) {
            $queryBuilder = \sw_query(ProductEntity::class);

            $startTime = microtime(true);

            $result = $queryBuilder
                ->where('active = true')
                ->where('stock > 10')
                ->orderBy('name', 'ASC')
                ->limit(20)
                ->get();

            $endTime = microtime(true);
            $executionTimes[] = $endTime - $startTime;

            $this->assertGreaterThan(0, $result->count());
        }

        // Calculate variance in execution times
        $avgTime = array_sum($executionTimes) / count($executionTimes);
        $variance = 0;

        foreach ($executionTimes as $time) {
            $variance += ($time - $avgTime) ** 2;
        }

        $variance /= count($executionTimes);
        $stdDev = sqrt($variance);

        // Standard deviation should be low (consistent performance)
        $this->assertLessThan(0.5, $stdDev, 'Query execution time too inconsistent');
    }

    /**
     * Test optimized vs non-optimized queries.
     */
    public function testOptimizedQueryVsNonOptimized(): void
    {
        // Non-optimized: Load all data then filter (simulating inefficient approach)
        $startTime1 = microtime(true);

        $allProducts = \sw_query(ProductEntity::class)
            ->limit(200)
            ->get();

        $filtered = [];
        foreach ($allProducts as $product) {
            if ($product->getActive() && $product->getStock() > 10) {
                $filtered[] = $product;

                if (count($filtered) >= 20) {
                    break;
                }
            }
        }

        $endTime1 = microtime(true);
        $nonOptimizedTime = $endTime1 - $startTime1;

        // Optimized: Use database filtering
        $startTime2 = microtime(true);

        $optimized = \sw_query(ProductEntity::class)
            ->where('active = true')
            ->where('stock > 10')
            ->limit(20)
            ->get();

        $endTime2 = microtime(true);
        $optimizedTime = $endTime2 - $startTime2;

        // Both should return similar results
        $this->assertGreaterThan(0, count($filtered), 'Non-optimized approach should find products');
        $this->assertGreaterThan(0, $optimized->count(), 'Optimized approach should find products');

        // Both should execute in reasonable time (less than 3 seconds)
        $this->assertLessThan(3.0, $nonOptimizedTime, 'Non-optimized took too long');
        $this->assertLessThan(3.0, $optimizedTime, 'Optimized took too long');
    }

    /**
     * Test concurrent-like query execution.
     */
    public function testMultipleSequentialQueries(): void
    {
        $startTime = microtime(true);

        // Execute multiple different queries sequentially
        $query1 = \sw_query(ProductEntity::class)
            ->where('active = true')
            ->limit(10)
            ->get();

        $query2 = \sw_query(ProductEntity::class)
            ->where('stock > 50')
            ->limit(10)
            ->get();

        $query3 = \sw_query(ProductEntity::class)
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->limit(10)
            ->get();

        $query4 = \sw_query(ProductEntity::class)
            ->addCount('id', 'count')
            ->get();

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $this->assertInstanceOf(EntitySearchResult::class, $query1);
        $this->assertInstanceOf(EntitySearchResult::class, $query2);
        $this->assertInstanceOf(EntitySearchResult::class, $query3);
        $this->assertInstanceOf(EntitySearchResult::class, $query4);

        // All 4 queries should execute in reasonable time
        $this->assertLessThan(3.0, $totalTime, 'Sequential queries took too long');
    }

    /**
     * Test query with all features combined.
     */
    public function testFullFeaturedQueryPerformance(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('stock > 100')
                    ->orWhere('stock = 0');
            })
            ->with('manufacturer')
            ->with('categories')
            ->with('tax')
            ->orderBy('stock', 'DESC')
            ->orderBy('name', 'ASC')
            ->addCount('id', 'count')
            ->addSum('stock', 'sum')
            ->addAvg('stock', 'avg')
            ->limit(50)
            ->offset(0)
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertInstanceOf(EntitySearchResult::class, $result);
        $this->assertNotNull($result->getAggregations()->get('count'));

        // Even with all features, query should be performant
        $this->assertLessThan(3.0, $executionTime, 'Full-featured query took too long');
    }

    /**
     * Test filtered aggregation performance.
     */
    public function testFilteredAggregationPerformance(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $startTime = microtime(true);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->where('stock < 100')
            ->whereNotNull('manufacturerId')
            ->addCount('id', 'filtered_count')
            ->addSum('stock', 'filtered_sum')
            ->addAvg('stock', 'filtered_avg')
            ->addMin('stock', 'filtered_min')
            ->addMax('stock', 'filtered_max')
            ->limit(0)
            ->get();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $aggregations = $result->getAggregations();
        $this->assertNotNull($aggregations->get('filtered_count'));
        $this->assertNotNull($aggregations->get('filtered_sum'));

        // Filtered aggregations should be fast
        $this->assertLessThan(2.0, $executionTime, 'Filtered aggregations took too long');
    }

    /**
     * Test association loading performance.
     */
    public function testAssociationLoadingPerformance(): void
    {
        // Query without associations
        $startTime1 = microtime(true);

        $withoutAssoc = \sw_query(ProductEntity::class)
            ->where('active = true')
            ->limit(50)
            ->get();

        $endTime1 = microtime(true);
        $timeWithoutAssoc = $endTime1 - $startTime1;

        // Query with associations
        $startTime2 = microtime(true);

        $withAssoc = \sw_query(ProductEntity::class)
            ->where('active = true')
            ->with('manufacturer')
            ->with('categories')
            ->limit(50)
            ->get();

        $endTime2 = microtime(true);
        $timeWithAssoc = $endTime2 - $startTime2;

        $this->assertGreaterThan(0, $withoutAssoc->count());
        $this->assertGreaterThan(0, $withAssoc->count());

        // Association loading adds overhead but should not be excessive
        $overhead = $timeWithAssoc - $timeWithoutAssoc;
        $this->assertLessThan(2.0, $overhead, 'Association loading overhead too high');
    }

    /**
     * Test query plan efficiency with BETWEEN vs multiple comparisons.
     */
    public function testBetweenVsMultipleComparisons(): void
    {
        // Using BETWEEN
        $startTime1 = microtime(true);

        $betweenResult = \sw_query(ProductEntity::class)
            ->whereBetween('stock', 10, 100)
            ->get();

        $endTime1 = microtime(true);
        $betweenTime = $endTime1 - $startTime1;

        // Using separate comparisons
        $startTime2 = microtime(true);

        $comparisonResult = \sw_query(ProductEntity::class)
            ->where('stock >= 10')
            ->where('stock <= 100')
            ->get();

        $endTime2 = microtime(true);
        $comparisonTime = $endTime2 - $startTime2;

        // Results should be same
        $this->assertEquals($betweenResult->count(), $comparisonResult->count());

        // Both should execute in reasonable time
        $this->assertLessThan(2.0, $betweenTime);
        $this->assertLessThan(2.0, $comparisonTime);
    }
}
