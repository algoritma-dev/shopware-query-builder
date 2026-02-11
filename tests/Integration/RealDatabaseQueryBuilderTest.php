<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\Exception\UpdateEntityException;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Uuid\Uuid;

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
            ->where('active = true')
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
            ->where('stock > 0')
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
            ->where('active = true')
            ->where('stock > 0')
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
            ->whereStartsWith('productNumber', 'SW-PROD')
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
            ->where('active = true')
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
            ->where('stock > 0')
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
            ->where('active = true')
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
            ->where('active = true')
            ->get();
        $this->assertGreaterThan(0, $result->count());
        $first = $result->first();
        $this->assertInstanceOf(ProductEntity::class, $first);
    }

    /**
     * Test that we can update a single product.
     */
    public function testUpdateSingleEntity(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $result = $queryBuilder
            ->update([[
                'id' => $this->getRepository(ProductEntity::class)->search(new Criteria(), $this->context)->first()->getId(),
                'name' => 'Updated Product Name',
            ]]);

        $this->assertInstanceOf(ProductEntity::class, $result);
        $this->assertSame('Updated Product Name', $result->getName());
    }

    /**
     * Test that we can update multiple products.
     */
    public function testUpdateMultipleEntity(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $result = $queryBuilder
            ->update([
                [
                    'id' => $this->getRepository(ProductEntity::class)->search((new Criteria())->addFilter(new EqualsFilter('productNumber', 'SW-PROD-001')), $this->context)->first()->getId(),
                    'name' => 'Updated Product Name 1',
                ],
                [
                    'id' => $this->getRepository(ProductEntity::class)->search((new Criteria())->addFilter(new EqualsFilter('productNumber', 'SW-PROD-002')), $this->context)->first()->getId(),
                    'name' => 'Updated Product Name 2',
                ],
            ]);

        $this->assertInstanceOf(ProductCollection::class, $result);
        $this->assertSame('Updated Product Name 1', $result->first()->getName());
        $this->assertSame('Updated Product Name 2', $result->last()->getName());
    }

    public function testUpdateWithoutIdShouldThrowException(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $this->expectException(WriteTypeIntendException::class);

        $queryBuilder
            ->update([
                [
                    'name' => 'Updated Product Name',
                ],
            ]);
    }

    public function testUpdateWithConditionAndDataNotValidThrowError(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $this->expectException(UpdateEntityException::class);

        $queryBuilder
            ->where('active = true')
            ->update([
                [
                    'name' => 'Updated Product Name',
                ],
            ]);
    }

    public function testUpdateWithCondition(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $result = $queryBuilder
            ->where('active = true')
            ->update([
                'name' => 'Updated Product Name',
            ]);

        foreach ($result as $product) {
            $this->assertSame('Updated Product Name', $product->getName());
        }
    }

    /**
     * Test that we can insert a single product.
     */
    public function testInsertSingleEntity(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $randomId = Uuid::randomHex();

        $result = $queryBuilder
            ->insert([[
                'name' => 'Inserted Product Name',
                'productNumber' => 'SW-PROD-' . $randomId,
                'tax' => ['name' => 'Standard Tax', 'taxRate' => 19.0],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'net' => 70.0, 'gross' => 83.3, 'linked' => false],
                ],
                'stock' => 10,
            ]]);

        $this->assertInstanceOf(ProductEntity::class, $result);
        $this->assertSame('Inserted Product Name', $result->getName());
        $this->assertSame('SW-PROD-' . $randomId, $result->getProductNumber());
        $this->assertSame(10, $result->getStock());
    }

    /**
     * Test that we can insert multiple products.
     */
    public function testInsertMultipleEntity(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $randomId = Uuid::randomHex();

        $result = $queryBuilder
            ->insert([
                [
                    'name' => 'Inserted Product Name 1',
                    'productNumber' => 'SW-PROD-' . Uuid::randomHex(),
                    'tax' => ['name' => 'Standard Tax', 'taxRate' => 19.0],
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'net' => 70.0, 'gross' => 83.3, 'linked' => false],
                    ],
                    'stock' => 10,
                ],
                [
                    'id' => $randomId,
                    'name' => 'Inserted Product Name 2',
                    'productNumber' => 'SW-PROD-' . $randomId,
                    'tax' => ['name' => 'Standard Tax', 'taxRate' => 19.0],
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'net' => 70.0, 'gross' => 83.3, 'linked' => false],
                    ],
                    'stock' => 20,
                ],
            ]);

        $this->assertInstanceOf(ProductCollection::class, $result);

        $firstProduct = $result->first();
        $this->assertInstanceOf(ProductEntity::class, $firstProduct);
        $this->assertSame('Inserted Product Name 1', $firstProduct->getName());
        $this->assertSame(10, $firstProduct->getStock());

        $lastProduct = $result->last();
        $this->assertInstanceOf(ProductEntity::class, $lastProduct);
        $this->assertSame($randomId, $lastProduct->getId());
        $this->assertSame('Inserted Product Name 2', $lastProduct->getName());
        $this->assertSame(20, $lastProduct->getStock());
    }
}
