<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for complex joins and nested associations.
 *
 * Tests multi-level associations, filtering on associated data, and complex joins.
 */
#[CoversNothing]
class RealDatabaseComplexJoinsTest extends KernelAwareTestCase
{
    /**
     * Test loading product with nested associations.
     */
    public function testNestedAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('manufacturer')
            ->with('categories')
            ->with('tax')
            ->limit(5)
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $this->assertNotNull($product->getManufacturer());
            $this->assertNotNull($product->getCategories());
            $this->assertNotNull($product->getTax());
        }
    }

    /**
     * Test filtering on associated manufacturer name.
     */
    public function testFilterByManufacturerName(): void
    {
        // First get a manufacturer name
        $queryBuilder = \sw_query(ProductEntity::class);
        $sample = $queryBuilder
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->limit(1)
            ->get();

        if ($sample->count() === 0) {
            $this->markTestSkipped('No products with manufacturer found');
        }

        $manufacturerName = $sample->first()->getManufacturer()->getName();

        // Now filter by that manufacturer name
        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result = $queryBuilder2
            ->where('manufacturer.name = "' . $manufacturerName . '"')
            ->with('manufacturer')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $this->assertEquals($manufacturerName, $product->getManufacturer()->getName());
        }
    }

    /**
     * Test combining filters on main entity and associations.
     */
    public function testCombinedEntityAndAssociationFilters(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertGreaterThan(0, $product->getStock());
            $this->assertNotNull($product->getManufacturer());
        }
    }

    /**
     * Test loading multiple levels of associations.
     */
    public function testMultiLevelAssociations(): void
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
            ->limit(3)
            ->get();

        foreach ($result as $product) {
            $this->assertNotNull($product->getManufacturer());
            $this->assertNotNull($product->getCategories());
            $this->assertNotNull($product->getTax());
            // Cover might be null for some products
        }
    }

    /**
     * Test filtering with IN on association field.
     */
    public function testFilterWithInOnAssociation(): void
    {
        // Get some manufacturer IDs
        $queryBuilder = \sw_query(ProductEntity::class);
        $sample = $queryBuilder
            ->whereNotNull('manufacturerId')
            ->limit(3)
            ->get();

        if ($sample->count() < 2) {
            $this->markTestSkipped('Not enough products with manufacturers');
        }

        $manufacturerIds = array_unique(
            array_map(fn (Entity $p) => $p->getManufacturerId(), $sample->getElements())
        );
        $manufacturerIds = array_slice($manufacturerIds, 0, 2);

        // Filter by those manufacturer IDs
        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result = $queryBuilder2
            ->whereIn('manufacturerId', $manufacturerIds)
            ->with('manufacturer')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $this->assertContains($product->getManufacturerId(), $manufacturerIds);
        }
    }

    /**
     * Test complex filter on category association.
     */
    public function testFilterByCategoryId(): void
    {
        // Get a product with categories
        $queryBuilder = \sw_query(ProductEntity::class);
        $sample = $queryBuilder
            ->with('categories')
            ->limit(10)
            ->get();

        $productWithCategories = null;
        foreach ($sample as $product) {
            if ($product->getCategories() && $product->getCategories()->count() > 0) {
                $productWithCategories = $product;
                break;
            }
        }

        if (! $productWithCategories instanceof Entity) {
            $this->markTestSkipped('No products with categories found');
        }

        $categoryId = $productWithCategories->getCategories()->first()->getId();

        // Query products in that category
        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result = $queryBuilder2
            ->where('categories.id = "' . $categoryId . '"')
            ->with('categories')
            ->get();

        $this->assertGreaterThan(0, $result->count());
    }

    /**
     * Test sorting by association field.
     */
    public function testSortByAssociationField(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->orderBy('manufacturer.name', 'ASC')
            ->limit(10)
            ->get();

        $this->assertGreaterThan(0, $result->count());

        // Verify manufacturers are sorted
        $names = array_values(array_map(
            fn (Entity $p) => $p->getManufacturer()->getName(),
            $result->getElements()
        ));
        $sortedNames = $names;
        sort($sortedNames);

        $this->assertEquals($sortedNames, $names, 'Should be sorted by manufacturer name');
    }

    /**
     * Test associations with OR conditions.
     */
    public function testAssociationsWithOrConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->whereNotNull('manufacturerId')
                    ->orWhere('stock > 100');
            })
            ->with('manufacturer')
            ->get();

        foreach ($result as $product) {
            $this->assertTrue(
                $product->getManufacturerId() !== null || $product->getStock() > 100
            );
        }
    }

    /**
     * Test loading associations with nested WHERE conditions.
     */
    public function testAssociationsWithNestedConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->where('active = true')
                    ->orWhere(function (QueryBuilder $q): void {
                        $q->whereNotNull('manufacturerId')
                            ->orWhere('stock > 50');
                    });
            })
            ->with('manufacturer')
            ->with('categories')
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
        }
    }

    /**
     * Test BETWEEN with associations.
     */
    public function testBetweenWithAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereBetween('stock', 10, 100)
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->get();

        foreach ($result as $product) {
            $this->assertGreaterThanOrEqual(10, $product->getStock());
            $this->assertLessThanOrEqual(100, $product->getStock());
            $this->assertNotNull($product->getManufacturer());
        }
    }

    /**
     * Test aggregations with associations.
     */
    public function testAggregationsWithJoins(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->addCount('id', 'product_count')
            ->addSum('stock', 'total_stock')
            ->get();

        $countResult = $result->getAggregations()->get('product_count');
        $this->assertGreaterThan(0, $countResult->getCount());
    }

    /**
     * Test multiple associations with LIMIT and OFFSET.
     */
    public function testMultipleAssociationsWithPagination(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('manufacturer')
            ->with('categories')
            ->with('tax')
            ->orderBy('productNumber', 'ASC')
            ->limit(5)
            ->offset(0)
            ->get();

        $this->assertLessThanOrEqual(5, $result->count());

        foreach ($result as $product) {
            $this->assertNotNull($product->getManufacturer());
            $this->assertNotNull($product->getCategories());
        }
    }

    /**
     * Test NOT NULL on association with OR.
     */
    public function testNotNullOnAssociationWithOr(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orWhere(function (QueryBuilder $query): void {
                $query->whereNotNull('manufacturerId')
                    ->orWhere('active = false');
            })
            ->with('manufacturer')
            ->limit(10)
            ->get();

        foreach ($result as $product) {
            $this->assertTrue(
                $product->getManufacturerId() !== null || ! $product->getActive()
            );
        }
    }

    /**
     * Test complex joins with multiple sorting.
     */
    public function testComplexJoinsWithMultipleSorting(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->orderBy('manufacturer.name', 'ASC')
            ->orderBy('productNumber', 'ASC')
            ->limit(10)
            ->get();

        $this->assertGreaterThan(0, $result->count());
    }

    /**
     * Test association loading with LIKE filter.
     */
    public function testAssociationsWithLikeFilter(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('productNumber LIKE "SW-%"')
            ->whereNotNull('manufacturerId')
            ->with('manufacturer')
            ->with('categories')
            ->limit(5)
            ->get();

        foreach ($result as $product) {
            $this->assertStringStartsWith('SW-', $product->getProductNumber());
            $this->assertNotNull($product->getManufacturer());
        }
    }

    /**
     * Test associations with mixed AND/OR conditions.
     */
    public function testAssociationsWithMixedConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->orWhere(function (QueryBuilder $query): void {
                $query->whereNotNull('manufacturerId')
                    ->orWhere('stock = 0');
            })
            ->with('manufacturer')
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertTrue(
                $product->getManufacturerId() !== null || $product->getStock() === 0
            );
        }
    }
}
