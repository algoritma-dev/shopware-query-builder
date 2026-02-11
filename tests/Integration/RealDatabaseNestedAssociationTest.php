<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * Integration tests for nested associations.
 *
 * Tests relationships like customer.defaultShippingAddress.country.
 */
#[CoversNothing]
class RealDatabaseNestedAssociationTest extends KernelAwareTestCase
{
    /**
     * Test nested association using dot notation - customer.defaultShippingAddress.country.
     */
    public function testNestedAssociationWithDotNotation(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(CustomerEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('defaultShippingAddress.country', 'countryAlias')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $customer) {
            // Default shipping address should be loaded
            $this->assertNotNull($customer->getDefaultShippingAddress());

            // Country should be loaded via nested association
            $country = $customer->getDefaultShippingAddress()->getCountry();
            $this->assertNotNull($country);
            $this->assertNotNull($country->getName());
        }
    }

    /**
     * Test nested association using explicit chaining with aliases.
     */
    public function testNestedAssociationWithExplicitChaining(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(CustomerEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('defaultShippingAddress', 'addressAlias')
            ->with('addressAlias.country', 'countryAlias')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $customer) {
            // Default shipping address should be loaded
            $this->assertNotNull($customer->getDefaultShippingAddress());

            // Country should be loaded via nested association
            $country = $customer->getDefaultShippingAddress()->getCountry();
            $this->assertNotNull($country);
            $this->assertNotNull($country->getName());
        }
    }

    /**
     * Test filtering by nested association property.
     */
    public function testFilterByNestedAssociationProperty(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(CustomerEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('defaultShippingAddress.country', 'countryAlias')
            ->where('countryAlias.iso = "US"')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $customer) {
            $country = $customer->getDefaultShippingAddress()->getCountry();
            $this->assertNotNull($country);
            $this->assertEquals('US', $country->getIso());
        }
    }

    /**
     * Test multiple nested associations.
     */
    public function testMultipleNestedAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(CustomerEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('defaultShippingAddress.country', 'shippingCountry')
            ->with('defaultBillingAddress.country', 'billingCountry')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $customer) {
            // Shipping address and country
            $this->assertNotNull($customer->getDefaultShippingAddress());
            $this->assertNotNull($customer->getDefaultShippingAddress()->getCountry());

            // Billing address and country
            $this->assertNotNull($customer->getDefaultBillingAddress());
            $this->assertNotNull($customer->getDefaultBillingAddress()->getCountry());
        }
    }

    /**
     * Test nested association with sub-query callback.
     */
    public function testNestedAssociationWithCallback(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(CustomerEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('defaultShippingAddress', 'addressAlias', function (QueryBuilder $q) {
                $q->with('country', 'countryAlias');
            })
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $customer) {
            $this->assertNotNull($customer->getDefaultShippingAddress());
            $this->assertNotNull($customer->getDefaultShippingAddress()->getCountry());
        }
    }

    /**
     * Test ordering by nested association property.
     */
    public function testOrderByNestedAssociationProperty(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(CustomerEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('defaultShippingAddress.country', 'countryAlias')
            ->orderBy('countryAlias.name', 'ASC')
            ->get();

        $this->assertGreaterThan(0, $result->count());

        // Verify results are ordered
        $previousCountryName = null;
        foreach ($result as $customer) {
            $countryName = $customer->getDefaultShippingAddress()->getCountry()->getName();

            if ($previousCountryName !== null) {
                $this->assertGreaterThanOrEqual(
                    0,
                    strcmp($countryName, $previousCountryName),
                    'Countries should be ordered alphabetically'
                );
            }

            $previousCountryName = $countryName;
        }
    }

    /**
     * Test complex filtering with nested associations and OR conditions.
     */
    public function testComplexFilteringWithNestedAssociations(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = \sw_query(CustomerEntity::class);

        /** @var EntitySearchResult $result */
        $result = $queryBuilder
            ->where('active = true')
            ->with('defaultShippingAddress.country', 'shippingCountry')
            ->with('defaultBillingAddress.country', 'billingCountry')
            ->where(function (QueryBuilder $q) {
                $q->where('shippingCountry.iso = "US"')
                    ->orWhere('billingCountry.iso = "DE"');
            })
            ->get();

        $this->assertGreaterThan(0, $result->count());

        foreach ($result as $customer) {
            $shippingIso = $customer->getDefaultShippingAddress()->getCountry()->getIso();
            $billingIso = $customer->getDefaultBillingAddress()->getCountry()->getIso();

            $this->assertTrue(
                $shippingIso === 'US' || $billingIso === 'DE',
                'Customer should have US shipping address OR DE billing address'
            );
        }
    }
}
