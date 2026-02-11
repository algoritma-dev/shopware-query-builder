<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for aggregation queries.
 *
 * Tests COUNT, SUM, AVG, MIN, MAX aggregations with various conditions.
 */
#[CoversNothing]
class RealDatabaseAggregationTest extends KernelAwareTestCase
{
    /**
     * Test COUNT aggregation on all products.
     */
    public function testCountAggregation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->addCount('id', 'product_count')
            ->get();

        $aggregations = $result->getAggregations();
        $this->assertNotNull($aggregations);

        $countResult = $aggregations->get('product_count');
        $this->assertInstanceOf(CountResult::class, $countResult);

        $count = $countResult->getCount();
        $this->assertGreaterThan(0, $count);
    }

    /**
     * Test COUNT aggregation with WHERE condition.
     */
    public function testCountAggregationWithFilter(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->addCount('id', 'active_product_count')
            ->get();

        $countResult = $result->getAggregations()->get('active_product_count');
        $this->assertInstanceOf(CountResult::class, $countResult);
        $this->assertGreaterThan(0, $countResult->getCount());
    }

    /**
     * Test SUM aggregation on stock.
     */
    public function testSumAggregation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->addSum('stock', 'total_stock')
            ->get();

        $sumResult = $result->getAggregations()->get('total_stock');
        $this->assertInstanceOf(SumResult::class, $sumResult);

        $sum = $sumResult->getSum();
        $this->assertGreaterThanOrEqual(0, $sum);
    }

    /**
     * Test SUM aggregation with WHERE condition.
     */
    public function testSumAggregationWithFilter(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->addSum('stock', 'active_total_stock')
            ->get();

        $sumResult = $result->getAggregations()->get('active_total_stock');
        $this->assertInstanceOf(SumResult::class, $sumResult);
        $this->assertGreaterThanOrEqual(0, $sumResult->getSum());
    }

    /**
     * Test AVG aggregation on stock.
     */
    public function testAvgAggregation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->addAvg('stock', 'avg_stock')
            ->get();

        $avgResult = $result->getAggregations()->get('avg_stock');
        $this->assertInstanceOf(AvgResult::class, $avgResult);

        $avg = $avgResult->getAvg();
        $this->assertGreaterThanOrEqual(0, $avg);
    }

    /**
     * Test AVG aggregation with WHERE condition.
     */
    public function testAvgAggregationWithFilter(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock > 0')
            ->addAvg('stock', 'avg_stock_in_stock')
            ->get();

        $avgResult = $result->getAggregations()->get('avg_stock_in_stock');
        $this->assertInstanceOf(AvgResult::class, $avgResult);
        $this->assertGreaterThan(0, $avgResult->getAvg());
    }

    /**
     * Test MIN aggregation on stock.
     */
    public function testMinAggregation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->addMin('stock', 'min_stock')
            ->get();

        $minResult = $result->getAggregations()->get('min_stock');
        $this->assertInstanceOf(MinResult::class, $minResult);

        $min = $minResult->getMin();
        $this->assertIsNumeric($min);
        $this->assertGreaterThanOrEqual(0, $min);
    }

    /**
     * Test MAX aggregation on stock.
     */
    public function testMaxAggregation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->addMax('stock', 'max_stock')
            ->get();

        $maxResult = $result->getAggregations()->get('max_stock');
        $this->assertInstanceOf(MaxResult::class, $maxResult);

        $max = $maxResult->getMax();
        $this->assertIsNumeric($max);
        $this->assertGreaterThanOrEqual(0, $max);
    }

    /**
     * Test multiple aggregations in a single query.
     */
    public function testMultipleAggregations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->addCount('id', 'product_count')
            ->addSum('stock', 'total_stock')
            ->addAvg('stock', 'avg_stock')
            ->addMin('stock', 'min_stock')
            ->addMax('stock', 'max_stock')
            ->get();

        $aggregations = $result->getAggregations();

        // Verify all aggregations are present
        $this->assertInstanceOf(CountResult::class, $aggregations->get('product_count'));
        $this->assertInstanceOf(SumResult::class, $aggregations->get('total_stock'));
        $this->assertInstanceOf(AvgResult::class, $aggregations->get('avg_stock'));
        $this->assertInstanceOf(MinResult::class, $aggregations->get('min_stock'));
        $this->assertInstanceOf(MaxResult::class, $aggregations->get('max_stock'));
    }

    /**
     * Test aggregations with complex WHERE conditions.
     */
    public function testAggregationsWithComplexFilters(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->addCount('id', 'filtered_count')
            ->addSum('stock', 'filtered_sum')
            ->get();

        $countResult = $result->getAggregations()->get('filtered_count');
        $sumResult = $result->getAggregations()->get('filtered_sum');

        $this->assertInstanceOf(CountResult::class, $countResult);
        $this->assertInstanceOf(SumResult::class, $sumResult);
        $this->assertGreaterThan(0, $countResult->getCount());
        $this->assertGreaterThan(0, $sumResult->getSum());
    }

    /**
     * Test aggregations with BETWEEN condition.
     */
    public function testAggregationsWithBetween(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereBetween('stock', 10, 100)
            ->addCount('id', 'mid_range_count')
            ->addAvg('stock', 'mid_range_avg')
            ->get();

        $countResult = $result->getAggregations()->get('mid_range_count');
        $avgResult = $result->getAggregations()->get('mid_range_avg');

        $this->assertInstanceOf(CountResult::class, $countResult);
        $this->assertInstanceOf(AvgResult::class, $avgResult);

        // Average should be between 10 and 100
        if ($countResult->getCount() > 0) {
            $avg = $avgResult->getAvg();
            $this->assertGreaterThanOrEqual(10, $avg);
            $this->assertLessThanOrEqual(100, $avg);
        }
    }

    /**
     * Test aggregations with OR conditions.
     */
    public function testAggregationsWithOrConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true OR stock > 100')
            ->addCount('id', 'or_count')
            ->addSum('stock', 'or_sum')
            ->get();

        $countResult = $result->getAggregations()->get('or_count');
        $sumResult = $result->getAggregations()->get('or_sum');

        $this->assertInstanceOf(CountResult::class, $countResult);
        $this->assertInstanceOf(SumResult::class, $sumResult);
    }

    /**
     * Test MIN and MAX together to verify range.
     */
    public function testMinMaxRange(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock > 0')
            ->addMin('stock', 'min_stock')
            ->addMax('stock', 'max_stock')
            ->get();

        $minResult = $result->getAggregations()->get('min_stock');
        $maxResult = $result->getAggregations()->get('max_stock');

        $this->assertInstanceOf(MinResult::class, $minResult);
        $this->assertInstanceOf(MaxResult::class, $maxResult);

        $min = $minResult->getMin();
        $max = $maxResult->getMax();

        $this->assertGreaterThan(0, $min);
        $this->assertGreaterThanOrEqual($min, $max, 'Max should be >= Min');
    }

    /**
     * Test aggregation on products with associations loaded.
     */
    public function testAggregationsWithAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->addCount('id', 'products_with_manufacturer')
            ->get();

        $countResult = $result->getAggregations()->get('products_with_manufacturer');
        $this->assertInstanceOf(CountResult::class, $countResult);
        $this->assertGreaterThan(0, $countResult->getCount());

        // Verify associations are still loaded
        if ($result->count() > 0) {
            $firstProduct = $result->first();
            $this->assertNotNull($firstProduct->getManufacturer());
        }
    }

    /**
     * Test COUNT with nested conditions.
     */
    public function testCountWithNestedConditions(): void
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
                    ->where('stock > 200');
            })
            ->addCount('id', 'complex_count')
            ->get();

        $countResult = $result->getAggregations()->get('complex_count');
        $this->assertInstanceOf(CountResult::class, $countResult);
    }

    /**
     * Test SUM, AVG together to verify calculation consistency.
     */
    public function testSumAvgConsistency(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock > 0')
            ->addCount('id', 'product_count')
            ->addSum('stock', 'total_stock')
            ->addAvg('stock', 'avg_stock')
            ->get();

        $countResult = $result->getAggregations()->get('product_count');
        $sumResult = $result->getAggregations()->get('total_stock');
        $avgResult = $result->getAggregations()->get('avg_stock');

        $this->assertInstanceOf(CountResult::class, $countResult);
        $this->assertInstanceOf(SumResult::class, $sumResult);
        $this->assertInstanceOf(AvgResult::class, $avgResult);

        if ($countResult->getCount() > 0) {
            $expectedAvg = $sumResult->getSum() / $countResult->getCount();
            $actualAvg = $avgResult->getAvg();

            // Allow small floating point differences
            $this->assertEqualsWithDelta($expectedAvg, $actualAvg, 0.01, 'AVG should equal SUM/COUNT');
        }
    }

    /**
     * Test aggregations with LIMIT (aggregations should ignore limit).
     */
    public function testAggregationsWithLimit(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->limit(5)
            ->addCount('id', 'total_count')
            ->get();

        // Limit affects returned entities but not aggregations
        $this->assertLessThanOrEqual(5, $result->count());

        $countResult = $result->getAggregations()->get('total_count');
        $this->assertInstanceOf(CountResult::class, $countResult);

        // Count should be total matching records, not limited to 5
        $this->assertGreaterThanOrEqual($result->count(), $countResult->getCount());
    }

    /**
     * Test aggregations with sorting.
     */
    public function testAggregationsWithSorting(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orderBy('stock', 'DESC')
            ->addCount('id', 'product_count')
            ->addMax('stock', 'max_stock')
            ->get();

        $countResult = $result->getAggregations()->get('product_count');
        $maxResult = $result->getAggregations()->get('max_stock');

        $this->assertInstanceOf(CountResult::class, $countResult);
        $this->assertInstanceOf(MaxResult::class, $maxResult);

        // Verify entities are sorted even with aggregations
        if ($result->count() > 1) {
            $stocks = array_values(array_map(fn (Entity $p) => $p->getStock(), $result->getElements()));
            $sortedStocks = $stocks;
            rsort($sortedStocks);
            $this->assertEquals($sortedStocks, $stocks, 'Results should be sorted DESC by stock');
        }
    }
}
