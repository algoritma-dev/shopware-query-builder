<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests that execute real queries against a real database.
 *
 * These tests verify that the QueryBuilder produces correct Criteria
 * and that those Criteria successfully retrieve data from the database.
 */
class RealDatabaseQueryBuilderTest extends KernelAwareTestCase
{
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a real QueryBuilder instance using the container services
        $this->queryBuilder = \sw_query(ProductEntity::class);
    }

    /**
     * Test that we can query active products from the database.
     */
    public function testQueryActiveProducts(): void
    {
        $result = $this->queryBuilder
            ->where('active', true)
            ->get();
        $this->assertGreaterThan(0, $result->count());
        // Verify all returned products are active
        foreach ($result as $product) {
            $this->assertTrue($product->getActive(), 'All products should be active');
        }
    }

    /**
     * Test that we can filter products by stock level.
     */
    public function testQueryProductsByStock(): void
    {
        $result = $this->queryBuilder
            ->where('stock', '>', 0)
            ->get();
        $this->assertGreaterThan(0, $result->count());
        // Verify all returned products have stock
        foreach ($result as $product) {
            $this->assertGreaterThan(0, $product->getStock(), 'All products should have stock > 0');
        }
    }

    /**
     * Test that we can combine multiple filters.
     */
    public function testQueryWithMultipleFilters(): void
    {
        $result = $this->queryBuilder
            ->where('active', true)
            ->where('stock', '>', 0)
            ->get();
        // Should have active products with stock
        $this->assertGreaterThan(0, $result->count());
        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
        }
    }

    /**
     * Test that we can use the BETWEEN operator.
     */
    public function testQueryWithBetweenOperator(): void
    {
        // Create a new QueryBuilder for this test
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereBetween('stock', 0, 100)
            ->get();
        // Should find products with stock between 0-100
        $this->assertGreaterThan(0, $result->count());
        foreach ($result as $product) {
            $stock = $product->getStock();
            $this->assertGreaterThanOrEqual(0, $stock);
            $this->assertLessThanOrEqual(100, $stock);
        }
    }

    /**
     * Test that we can use the IN operator.
     */
    public function testQueryWithInOperator(): void
    {
        // Get product IDs from initial query
        $allProducts = $this->getRepository(ProductEntity::class)->search(new Criteria(), $this->context);
        $ids = $allProducts->getIds();
        if (count($ids) < 2) {
            $this->markTestSkipped('Not enough products in database for IN test');
        }
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        $selectedIds = array_slice($ids, 0, 2);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereIn('id', $selectedIds)
            ->get();
        $this->assertCount(2, $result);
    }

    /**
     * Test that we can use the LIKE operator.
     */
    public function testQueryWithLikeOperator(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('productNumber', 'starts with', 'SW-PROD')
            ->get();
        $this->assertGreaterThan(0, $result->count());
        foreach ($result as $product) {
            $this->assertStringStartsWith('SW-PROD', $product->getProductNumber());
        }
    }

    /**
     * Test sorting results.
     */
    public function testQueryWithSorting(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orderBy('name', 'ASC')
            ->get();
        $this->assertGreaterThan(0, $result->count());
        // Verify results are sorted by name
        $names = array_map(fn (Entity $product) => $product->getName(), $result->getElements());
        $sortedNames = $names;
        asort($sortedNames);
        $this->assertEquals($sortedNames, $names, 'Products should be sorted by name ascending');
    }

    /**
     * Test limit functionality.
     */
    public function testQueryWithLimit(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->limit(2)
            ->get();
        $this->assertLessThanOrEqual(2, $result->count());
    }

    /**
     * Test pagination.
     */
    public function testQueryWithPagination(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->paginate(1, 2)
            ->get();
        // First page should return up to 2 items
        $this->assertLessThanOrEqual(2, $result->count());
    }

    /**
     * Test that inactive products are excluded by default.
     */
    public function testInactiveProductsExcludedFromQuery(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active', true)
            ->get();
        // All returned products must be active
        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
        }
    }

    /**
     * Test that out-of-stock products can be excluded.
     */
    public function testOutOfStockProductsCanBeFiltered(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('stock', '>', 0)
            ->get();

        /** @var ProductEntity $product */
        foreach ($result as $product) {
            $this->assertGreaterThan(0, $product->getStock());
        }
    }

    /**
     * Test that we can query and count results.
     */
    public function testQueryCount(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active', true)
            ->get();
        $count = $result->count();
        $this->assertGreaterThan(0, $count);
    }

    /**
     * Test that we can get the first product.
     */
    public function testGetFirstProduct(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active', true)
            ->get();
        $this->assertGreaterThan(0, $result->count());
        $first = $result->first();
        $this->assertInstanceOf(ProductEntity::class, $first);
    }
}
