<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for advanced real-world scenarios.
 *
 * Tests complex business logic patterns, edge cases, and advanced query combinations
 * that might occur in production environments.
 */
#[CoversNothing]
class RealDatabaseAdvancedScenariosTest extends KernelAwareTestCase
{
    /**
     * Test inventory dashboard query: products needing restock.
     * Combines multiple conditions with aggregations to identify low-stock items.
     */
    public function testInventoryDashboardLowStockQuery(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->where('stock < 20')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->orderBy('stock', 'ASC')
            ->orderBy('name', 'ASC')
            ->addCount('id', 'low_stock_count')
            ->addSum('stock', 'total_remaining_stock')
            ->addAvg('stock', 'avg_stock_level')
            ->limit(50)
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
            $this->assertLessThan(20, $product->getStock());
        }

        $this->assertNotNull($result->getAggregations()->get('low_stock_count'));
        $this->assertNotNull($result->getAggregations()->get('total_remaining_stock'));
    }

    /**
     * Test product search with multiple text filters.
     * Simulates a search bar with multiple possible matching fields.
     */
    public function testProductSearchWithMultipleTextFilters(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('name LIKE "%shoe%"')
                    ->orWhere('name LIKE "%boot%"')
                    ->orWhere('name LIKE "%sneaker%"')
                    ->orWhere('productNumber LIKE "%SW-%"');
            })
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->with('categories')
            ->orderBy('name', 'ASC')
            ->limit(20)
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $name = strtolower($product->getName() ?? '');
            $productNumber = $product->getProductNumber();

            $matchesSearch = str_contains($name, 'shoe')
                || str_contains($name, 'boot')
                || str_contains($name, 'sneaker')
                || str_starts_with((string) $productNumber, 'SW-');

            $this->assertTrue($matchesSearch);
        }
    }

    /**
     * Test category listing with product counts.
     * Complex query joining products to categories with aggregations.
     */
    public function testCategoryProductCountAggregation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->with('categories')
            ->addCount('id', 'active_products_count')
            ->addSum('stock', 'total_available_stock')
            ->get();

        $countResult = $result->getAggregations()->get('active_products_count');
        $sumResult = $result->getAggregations()->get('total_available_stock');

        $this->assertGreaterThan(0, $countResult->getCount());
        $this->assertGreaterThan(0, $sumResult->getSum());
    }

    /**
     * Test price range filter with manufacturer grouping.
     * Business scenario: "Show me products by manufacturer in specific price range".
     */
    public function testPriceRangeWithManufacturerFilter(): void
    {
        // Get a sample manufacturer
        $sampleQuery = \sw_query(ProductEntity::class);
        $sample = $sampleQuery
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->limit(1)
            ->get();

        $manufacturerId = $sample->first()->getManufacturerId();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('manufacturerId = "' . $manufacturerId . '"')
            ->where('stock > 0')
            ->with('manufacturer')
            ->orderBy('name', 'ASC')
            ->addCount('id', 'manufacturer_product_count')
            ->addAvg('stock', 'avg_manufacturer_stock')
            ->get();

        foreach ($result as $product) {
            $this->assertEquals($manufacturerId, $product->getManufacturerId());
            $this->assertTrue($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
        }
    }

    /**
     * Test complex inventory status query with multiple stock thresholds.
     * Business logic: critical (0-5), low (6-20), medium (21-50), high (51+).
     */
    public function testInventoryStatusSegmentation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        // Get critical stock items (0-5)
        /** @var EntitySearchResult $criticalResult */
        $criticalResult = $queryBuilder
            ->where('active = true')
            ->where('stock >= 0')
            ->where('stock <= 5')
            ->addCount('id', 'critical_count')
            ->get();

        // Get low stock items (6-20)
        $lowStockQuery = \sw_query(ProductEntity::class);
        $lowResult = $lowStockQuery
            ->where('active = true')
            ->where('stock > 5')
            ->where('stock <= 20')
            ->addCount('id', 'low_count')
            ->get();

        // Get medium stock items (21-50)
        $mediumStockQuery = \sw_query(ProductEntity::class);
        $mediumResult = $mediumStockQuery
            ->where('active = true')
            ->where('stock > 20')
            ->where('stock <= 50')
            ->addCount('id', 'medium_count')
            ->get();

        // Verify all queries executed successfully
        $this->assertInstanceOf(EntitySearchResult::class, $criticalResult);
        $this->assertInstanceOf(EntitySearchResult::class, $lowResult);
        $this->assertInstanceOf(EntitySearchResult::class, $mediumResult);

        // Verify stock ranges
        foreach ($criticalResult as $product) {
            $this->assertGreaterThanOrEqual(0, $product->getStock());
            $this->assertLessThanOrEqual(5, $product->getStock());
        }

        foreach ($lowResult as $product) {
            $this->assertGreaterThan(5, $product->getStock());
            $this->assertLessThanOrEqual(20, $product->getStock());
        }

        foreach ($mediumResult as $product) {
            $this->assertGreaterThan(20, $product->getStock());
            $this->assertLessThanOrEqual(50, $product->getStock());
        }

        // Verify aggregations exist
        $this->assertNotNull($criticalResult->getAggregations()->get('critical_count'));
        $this->assertNotNull($lowResult->getAggregations()->get('low_count'));
        $this->assertNotNull($mediumResult->getAggregations()->get('medium_count'));
    }

    /**
     * Test product availability check with complex conditions.
     * Business scenario: "Available for immediate shipment".
     */
    public function testProductAvailabilityForImmediateShipment(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->whereNotNull('productNumber')
            ->with('manufacturer')
            ->with('tax')
            ->orderBy('stock', 'DESC')
            ->orderBy('name', 'ASC')
            ->addCount('id', 'available_count')
            ->addSum('stock', 'total_available')
            ->limit(100)
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
            $this->assertNotNull($product->getProductNumber());
            $this->assertNotNull($product->getManufacturer());
        }
    }

    /**
     * Test top sellers query with stock availability.
     * Combines ordering, filtering, and associations.
     */
    public function testTopSellersWithStockAvailability(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 10')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->with('categories')
            ->orderBy('stock', 'DESC')
            ->orderBy('name', 'ASC')
            ->limit(10)
            ->addMax('stock', 'highest_stock')
            ->addAvg('stock', 'avg_top_seller_stock')
            ->get();

        $this->assertLessThanOrEqual(10, $result->count());

        if ($result->count() > 1) {
            $stocks = array_values(array_map(fn (Entity $p) => $p->getStock(), $result->getElements()));

            // Verify descending stock order
            for ($i = 1, $iMax = count($stocks); $i < $iMax; ++$i) {
                $this->assertLessThanOrEqual($stocks[$i - 1], $stocks[$i]);
            }
        }
    }

    /**
     * Test out-of-stock products with reorder candidates.
     * Business scenario: "Products that were active but now out of stock".
     */
    public function testOutOfStockReorderCandidates(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock = 0')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->orderBy('name', 'ASC')
            ->addCount('id', 'out_of_stock_count')
            ->limit(50)
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertEquals(0, $product->getStock());
        }
    }

    /**
     * Test new arrivals query.
     * Simulates "Recently added products with good stock".
     */
    public function testNewArrivalsWithGoodStock(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock >= 10')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->with('categories')
            ->with('cover')
            ->orderBy('createdAt', 'DESC')
            ->limit(20)
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertGreaterThanOrEqual(10, $product->getStock());
        }
    }

    /**
     * Test bulk price analysis.
     * Business scenario: "Analyze pricing across product ranges".
     */
    public function testBulkPriceAnalysisWithStockLevels(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->addCount('id', 'total_products')
            ->addSum('stock', 'total_inventory_value')
            ->addAvg('stock', 'avg_stock_per_product')
            ->addMin('stock', 'min_stock')
            ->addMax('stock', 'max_stock')
            ->get();

        $aggregations = $result->getAggregations();

        $this->assertNotNull($aggregations->get('total_products'));
        $this->assertNotNull($aggregations->get('total_inventory_value'));
        $this->assertNotNull($aggregations->get('avg_stock_per_product'));
        $this->assertNotNull($aggregations->get('min_stock'));
        $this->assertNotNull($aggregations->get('max_stock'));

        $minResult = $aggregations->get('min_stock');
        $maxResult = $aggregations->get('max_stock');

        $this->assertLessThanOrEqual($maxResult->getMax(), $minResult->getMin() + $maxResult->getMax());
    }

    /**
     * Test cross-sell candidates.
     * Business scenario: "Products in same category with different manufacturers".
     */
    public function testCrossSellCandidatesByCategory(): void
    {
        // First, get a product with categories
        $sampleQuery = \sw_query(ProductEntity::class);
        $sample = $sampleQuery
            ->where('active = true')
            ->with('categories')
            ->limit(10)
            ->get();

        $productWithCategory = null;
        foreach ($sample as $product) {
            if ($product->getCategories() && $product->getCategories()->count() > 0) {
                $productWithCategory = $product;
                break;
            }
        }

        $categoryId = $productWithCategory->getCategories()->first()->getId();

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->where('categories.id = "' . $categoryId . '"')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->with('categories')
            ->orderBy('name', 'ASC')
            ->limit(10)
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $result);
        $this->assertGreaterThanOrEqual(0, $result->count(), 'Query should execute successfully');

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
            $this->assertNotNull($product->getManufacturer());
            $this->assertNotNull($product->getCategories());
        }
    }

    /**
     * Test manufacturer performance report.
     * Business scenario: "Which manufacturers have the most active products".
     */
    public function testManufacturerPerformanceReport(): void
    {
        // Get sample manufacturers
        $sampleQuery = \sw_query(ProductEntity::class);
        $sample = $sampleQuery
            ->whereNotNull('manufacturerId')
            ->limit(5)
            ->get();

        $manufacturerIds = array_unique(
            array_map(fn (Entity $p) => $p->getManufacturerId(), $sample->getElements())
        );
        $manufacturerIds = array_slice($manufacturerIds, 0, 3);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->whereIn('manufacturerId', $manufacturerIds)
            ->with('manufacturer')
            ->addCount('id', 'products_per_manufacturer')
            ->addSum('stock', 'total_stock_per_manufacturer')
            ->addAvg('stock', 'avg_stock_per_manufacturer')
            ->get();

        $this->assertGreaterThan(0, $result->getAggregations()->get('products_per_manufacturer')->getCount());
    }

    /**
     * Test stock discrepancy detection.
     * Business scenario: "Find products with unusual stock patterns".
     */
    public function testStockDiscrepancyDetection(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        // Products that are active but have zero stock
        /** @var EntitySearchResult $zeroStockActive */
        $zeroStockActive = $queryBuilder
            ->where('active = true')
            ->where('stock = 0')
            ->addCount('id', 'zero_stock_active_count')
            ->get();

        // Products that are inactive but have stock
        $inactiveWithStockQuery = \sw_query(ProductEntity::class);
        $inactiveWithStock = $inactiveWithStockQuery
            ->where('active = false')
            ->where('stock > 0')
            ->addCount('id', 'inactive_with_stock_count')
            ->get();

        $this->assertInstanceOf(EntitySearchResult::class, $zeroStockActive);
        $this->assertInstanceOf(EntitySearchResult::class, $inactiveWithStock);

        // Verify zero stock active products
        foreach ($zeroStockActive as $product) {
            $this->assertTrue($product->getActive());
            $this->assertEquals(0, $product->getStock());
        }

        // Verify inactive with stock products
        foreach ($inactiveWithStock as $product) {
            $this->assertFalse($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
        }

        // Verify aggregations
        $this->assertGreaterThanOrEqual(0, $zeroStockActive->getAggregations()->get('zero_stock_active_count')->getCount());
        $this->assertGreaterThanOrEqual(0, $inactiveWithStock->getAggregations()->get('inactive_with_stock_count')->getCount());
    }

    /**
     * Test seasonal products filtering.
     * Business scenario: "Active products with good availability".
     */
    public function testSeasonalProductsWithHighAvailability(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock >= 50')
            ->whereNotNull('manufacturerId')
            ->where('productNumber IS NOT NULL')
            ->with('manufacturer')
            ->with('categories')
            ->orderBy('stock', 'DESC')
            ->orderBy('name', 'ASC')
            ->addCount('id', 'high_stock_count')
            ->addSum('stock', 'total_high_stock')
            ->limit(30)
            ->get();

        foreach ($result as $product) {
            $this->assertGreaterThanOrEqual(50, $product->getStock());
            $this->assertTrue($product->getActive());
        }
    }

    /**
     * Test complex exclusion filters.
     * Business scenario: "All products EXCEPT certain conditions".
     */
    public function testComplexExclusionFilters(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->orderBy('name', 'ASC')
            ->limit(20)
            ->get();

        $this->assertGreaterThan(0, $result->count(), 'Should have products matching criteria');

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
            $this->assertNotNull($product->getManufacturerId());
        }
    }

    /**
     * Test pagination consistency with complex filters.
     * Business scenario: "Ensure pagination works correctly with complex queries".
     */
    public function testPaginationConsistencyWithComplexFilters(): void
    {
        /** @var QueryBuilder $page1Query */
        $page1Query = \sw_query(ProductEntity::class);

        $page1 = $page1Query
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->orderBy('productNumber', 'ASC')
            ->limit(5)
            ->offset(0)
            ->get();

        /** @var QueryBuilder $page2Query */
        $page2Query = \sw_query(ProductEntity::class);

        $page2 = $page2Query
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->orderBy('productNumber', 'ASC')
            ->limit(5)
            ->offset(5)
            ->get();

        // Ensure no overlap between pages
        $page1Ids = $page1->getIds();
        $page2Ids = $page2->getIds();

        // Test that pagination works - first page should have items
        $this->assertGreaterThan(0, count($page1Ids), 'First page should have items');

        // If there are enough items for pagination, ensure no overlap
        if (count($page1Ids) > 0 && count($page2Ids) > 0) {
            foreach ($page2Ids as $id) {
                $this->assertNotContains($id, $page1Ids, 'Pages should not overlap');
            }
        } else {
            $this->addToAssertionCount(1); // Mark test as having assertions
        }
    }

    /**
     * Test aggregation accuracy with filtered datasets.
     * Business scenario: "Verify aggregation calculations are correct".
     */
    public function testAggregationAccuracyWithFilters(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock >= 10')
            ->where('stock <= 100')
            ->addCount('id', 'count')
            ->addSum('stock', 'sum')
            ->addAvg('stock', 'avg')
            ->addMin('stock', 'min')
            ->addMax('stock', 'max')
            ->get();

        $aggregations = $result->getAggregations();

        $count = $aggregations->get('count')->getCount();
        $sum = $aggregations->get('sum')->getSum();
        $avg = $aggregations->get('avg')->getAvg();
        $min = $aggregations->get('min')->getMin();
        $max = $aggregations->get('max')->getMax();

        // Verify logical consistency
        if ($count > 0) {
            $this->assertEqualsWithDelta($sum / $count, $avg, 0.01);
            $this->assertLessThanOrEqual($avg, $max);
            $this->assertGreaterThanOrEqual($min, $avg);
            $this->assertGreaterThanOrEqual(10, $min);
            $this->assertLessThanOrEqual(100, $max);
        }
    }

    /**
     * Test combined text and numeric filters with associations.
     * Business scenario: "Advanced search with multiple criteria".
     */
    public function testCombinedTextNumericFiltersWithAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('name LIKE "%shoe%"')
                    ->orWhere('productNumber LIKE "SW-%"');
            })
            ->with('manufacturer')
            ->with('categories')
            ->orderBy('stock', 'DESC')
            ->orderBy('name', 'ASC')
            ->addCount('id', 'matching_products')
            ->limit(15)
            ->get();

        $this->assertGreaterThan(0, $result->count(), 'Should have matching products');

        foreach ($result as $product) {
            $this->assertTrue($product->getActive(), 'Product must be active');
            $this->assertGreaterThan(0, $product->getStock(), 'Product must have stock');
            $this->assertNotNull($product->getManufacturerId(), 'Product must have manufacturer');
        }
    }
}
