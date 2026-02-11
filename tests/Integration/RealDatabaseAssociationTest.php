<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for querying and loading associated data from the database.
 *
 * Tests relationships like products with manufacturers and categories.
 */
class RealDatabaseAssociationTest extends KernelAwareTestCase
{
    /**
     * Test that we can load products with their manufacturer association.
     */
    public function testLoadProductsWithManufacturer(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('manufacturer')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            // Manufacturer should be loaded
            $this->assertNotNull($product->getManufacturer());
            $this->assertNotNull($product->getManufacturer()->getName());
        }
    }

    /**
     * Test that we can load products with categories.
     */
    public function testLoadProductsWithCategories(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('categories')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            // Categories should be loaded
            $categories = $product->getCategories();
            $this->assertNotNull($categories);
            $this->assertGreaterThan(0, $categories->count());
        }
    }

    /**
     * Test that we can load multiple associations at once.
     */
    public function testLoadProductsWithMultipleAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('manufacturer')
            ->with('categories')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $this->assertNotNull($product->getManufacturer());
            $this->assertNotNull($product->getCategories());
            $this->assertGreaterThan(0, $product->getCategories()->count());
        }
    }

    /**
     * Test that we can filter by manufacturer association.
     */
    public function testFilterByManufacturer(): void
    {
        // First, get products to find a valid manufacturer
        $allProducts = $this->getRepository(ProductEntity::class)
            ->search(
                new Criteria(),
                $this->context
            );

        if ($allProducts->count() === 0) {
            $this->markTestSkipped('No products available for manufacturer filter test');
        }

        /** @var ProductEntity $product */
        $product = $allProducts->first();
        $manufacturerId = $product->getManufacturer()?->getId();

        if (! $manufacturerId) {
            $this->markTestSkipped('No manufacturer found for test product');
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('manufacturerId = "' . $manufacturerId . '"')
            ->with('manufacturer')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $this->assertEquals($manufacturerId, $product->getManufacturer()->getId());
        }
    }
}
