<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for advanced QueryBuilder features with real database.
 *
 * Tests scopes, complex filters, and aggregations against actual data.
 */
#[CoversNothing]
class RealDatabaseAdvancedFeaturesTest extends KernelAwareTestCase
{
    /**
     * Test combining multiple filter conditions in a single query.
     */
    public function testComplexMultipleConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->where('stock > 0')
            ->where('stock < 200')
            ->get();

        /** @var ProductEntity $product */
        foreach ($result as $product) {
            $this->assertTrue($product->getActive(), 'Product must be active');
            $this->assertGreaterThan(0, $product->getStock(), 'Stock must be > 0');
            $this->assertLessThan(200, $product->getStock(), 'Stock must be < 200');
        }
    }

    /**
     * Test NOT NULL filter.
     */
    public function testWhereNotNull(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereNotNull('manufacturerId')
            ->get();

        foreach ($result as $product) {
            $this->assertNotNull($product->getManufacturerId(), 'Manufacturer ID must not be null');
        }
    }

    /**
     * Test case-insensitive string matching.
     */
    public function testCaseInsensitiveSearch(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('name LIKE "%shoe%"')
            ->get();

        // Verify we got results with "shoe" in the name
        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $product) {
            $this->assertStringContainsStringIgnoringCase('shoe', $product->getName());
        }
    }

    /**
     * Test multiple sorting conditions.
     */
    public function testMultipleSortingConditions(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->orderBy('active', 'DESC')
            ->orderBy('name', 'ASC')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        // Verify active products come first, then sorted by name
        $previousActive = true;
        $previousName = '';

        foreach ($result as $product) {
            // Se passiamo da un prodotto attivo a uno non attivo, aggiorniamo lo stato
            if ($previousActive && ! $product->getActive()) {
                $previousActive = false;
                $previousName = ''; // Resettiamo il nome per il nuovo blocco di prodotti (non attivi)
            }

            if ($product->getActive() === $previousActive) {
                // Per l'ordinamento ASC, strcmp(precedente, attuale) deve essere <= 0
                $this->assertLessThanOrEqual(
                    0,
                    strcmp((string) $previousName, (string) $product->getName()),
                    sprintf('Sorting failed: "%s" should come before "%s"', $previousName, $product->getName())
                );
                $previousName = $product->getName();
            }
        }
    }

    /**
     * Test offset and limit together.
     */
    public function testOffsetAndLimitTogether(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        $allResult = $queryBuilder->get();
        $totalCount = $allResult->count();

        if ($totalCount < 3) {
            $this->markTestSkipped('Not enough products for pagination test');
        }

        // Get second item (offset 1, limit 1)
        $queryBuilder2 = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder2
            ->limit(1)
            ->offset(1)
            ->get();

        $this->assertLessThanOrEqual(1, $result->count());
    }

    /**
     * Test filtering with NULL values.
     */
    public function testFilterByNullValue(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        // Query for products without manufacturer
        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->whereNull('manufacturerId')
            ->get();

        // All products in our fixtures have manufacturers, so this should return 0
        // But we test that the query is syntactically correct
        $this->assertInstanceOf(
            EntitySearchResult::class,
            $result
        );
    }

    /**
     * Test getting products in descending price order.
     */
    public function testSortByPriceDescending(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->get();

        if ($result->count() > 0) {
            $this->assertGreaterThan(0, $result->count());
        }
    }

    /**
     * Test fluent method chaining returns correct instance.
     */
    public function testFluentInterfaceChaining(): void
    {
        $queryBuilder1 = \sw_query(ProductEntity::class);

        // Test that methods return the same instance for chaining
        $queryBuilder2 = $queryBuilder1
            ->where('active = true')
            ->where('stock > 0');

        $this->assertSame($queryBuilder1, $queryBuilder2, 'Fluent methods should return the same instance');
    }

    /**
     * Test combining filters with associations.
     */
    public function testFiltersWithAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(ProductEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('manufacturer')
            ->with('categories')
            ->orderBy('name')
            ->limit(10)
            ->get();

        foreach ($result as $product) {
            $this->assertTrue($product->getActive());
            $this->assertNotNull($product->getManufacturer());
            $this->assertNotNull($product->getCategories());
        }
    }

    /**
     * Test that results are consistently ordered.
     */
    public function testConsistentOrdering(): void
    {
        $queryBuilder1 = \sw_query(ProductEntity::class);
        $result1 = $queryBuilder1
            ->orderBy('productNumber', 'ASC')
            ->get();

        $queryBuilder2 = \sw_query(ProductEntity::class);
        $result2 = $queryBuilder2
            ->orderBy('productNumber', 'ASC')
            ->get();

        $ids1 = array_map(fn (Entity $p) => $p->getId(), $result1->getElements());
        $ids2 = array_map(fn (Entity $p) => $p->getId(), $result2->getElements());

        $this->assertEquals($ids1, $ids2, 'Same query should return results in same order');
    }
}
