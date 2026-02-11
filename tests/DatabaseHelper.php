<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * Helper class for managing test database and fixtures.
 */
class DatabaseHelper
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $manufacturerRepository,
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $paymentMethodRepository,
    ) {}

    /**
     * Load standard test fixtures into the database.
     */
    public function loadFixtures(Context $context): void
    {
        // Create manufacturers
        $manufacturerIds = [
            'manufacturer-1' => Uuid::randomHex(),
            'manufacturer-2' => Uuid::randomHex(),
        ];
        $manufacturers = [
            [
                'id' => $manufacturerIds['manufacturer-1'],
                'name' => 'Nike',
                'link' => 'https://www.nike.com',
            ],
            [
                'id' => $manufacturerIds['manufacturer-2'],
                'name' => 'Adidas',
                'link' => 'https://www.adidas.com',
            ],
        ];
        $this->manufacturerRepository->create($manufacturers, $context);
        // Create categories
        $categoryIds = [
            'category-1' => Uuid::randomHex(),
            'category-2' => Uuid::randomHex(),
        ];
        $categories = [
            [
                'id' => $categoryIds['category-1'],
                'name' => 'Shoes',
            ],
            [
                'id' => $categoryIds['category-2'],
                'name' => 'Clothing',
            ],
        ];
        $this->categoryRepository->create($categories, $context);
        // Create products
        $products = [
            [
                'id' => Uuid::randomHex(),
                'productNumber' => 'SW-PROD-001',
                'name' => 'Running Shoe Pro',
                'active' => true,
                'stock' => 50,
                'tax' => ['name' => 'Standard Tax', 'taxRate' => 19.0],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'net' => 70.0, 'gross' => 83.3, 'linked' => false],
                ],
                'manufacturerId' => $manufacturerIds['manufacturer-1'],
                'categories' => [
                    ['id' => $categoryIds['category-1']],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => 'SW-PROD-002',
                'name' => 'Casual T-Shirt',
                'active' => true,
                'stock' => 150,
                'tax' => ['name' => 'Standard Tax', 'taxRate' => 19.0],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'net' => 15.0, 'gross' => 17.85, 'linked' => false],
                ],
                'manufacturerId' => $manufacturerIds['manufacturer-2'],
                'categories' => [
                    ['id' => $categoryIds['category-2']],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => 'SW-PROD-003',
                'name' => 'Inactive Product',
                'active' => false,
                'stock' => 10,
                'tax' => ['name' => 'Standard Tax', 'taxRate' => 19.0],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'net' => 50.0, 'gross' => 59.5, 'linked' => false],
                ],
                'manufacturerId' => $manufacturerIds['manufacturer-1'],
                'categories' => [
                    ['id' => $categoryIds['category-1']],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => 'SW-PROD-004',
                'name' => 'Out of Stock Item',
                'active' => true,
                'stock' => 0,
                'tax' => ['name' => 'Standard Tax', 'taxRate' => 19.0],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'net' => 100.0, 'gross' => 119.0, 'linked' => false],
                ],
                'manufacturerId' => $manufacturerIds['manufacturer-2'],
                'categories' => [
                    ['id' => $categoryIds['category-2']],
                ],
            ],
        ];
        $this->productRepository->create($products, $context);

        // Create or fetch countries
        $countryIds = [
            'country-de' => Uuid::randomHex(),
            'country-us' => Uuid::randomHex(),
        ];
        $countries = [
            [
                'id' => $countryIds['country-de'],
                'name' => 'Germany',
                'iso' => 'DE',
                'iso3' => 'DEU',
                'position' => 1,
                'taxFree' => false,
                'active' => true,
                'shippingAvailable' => true,
            ],
            [
                'id' => $countryIds['country-us'],
                'name' => 'United States',
                'iso' => 'US',
                'iso3' => 'USA',
                'position' => 2,
                'taxFree' => false,
                'active' => true,
                'shippingAvailable' => true,
            ],
        ];
        $this->countryRepository->upsert($countries, $context);

        // Create customers with addresses
        $customerIds = [
            'customer-1' => Uuid::randomHex(),
            'customer-2' => Uuid::randomHex(),
        ];
        $addressIds = [
            'address-1-billing' => Uuid::randomHex(),
            'address-1-shipping' => Uuid::randomHex(),
            'address-2-billing' => Uuid::randomHex(),
            'address-2-shipping' => Uuid::randomHex(),
        ];
        $customers = [
            [
                'id' => $customerIds['customer-1'],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'defaultPaymentMethodId' => '11491d8f829143c9a1f15c9c55e3df0c',
                'defaultBillingAddressId' => $addressIds['address-1-billing'],
                'defaultShippingAddressId' => $addressIds['address-1-shipping'],
                'customerNumber' => 'CUST-001',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'john.doe@example.com',
                'active' => true,
                'guest' => false,
                'addresses' => [
                    [
                        'id' => $addressIds['address-1-billing'],
                        'countryId' => $countryIds['country-de'],
                        'street' => 'Musterstraße 1',
                        'zipcode' => '12345',
                        'city' => 'Berlin',
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                    ],
                    [
                        'id' => $addressIds['address-1-shipping'],
                        'countryId' => $countryIds['country-us'],
                        'street' => '123 Main Street',
                        'zipcode' => '10001',
                        'city' => 'New York',
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                    ],
                ],
            ],
            [
                'id' => $customerIds['customer-2'],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'defaultPaymentMethodId' => $this->getDefaultPaymentMethodId($context),
                'defaultBillingAddressId' => $addressIds['address-2-billing'],
                'defaultShippingAddressId' => $addressIds['address-2-shipping'],
                'customerNumber' => 'CUST-002',
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'email' => 'jane.smith@example.com',
                'active' => true,
                'guest' => false,
                'addresses' => [
                    [
                        'id' => $addressIds['address-2-billing'],
                        'countryId' => $countryIds['country-de'],
                        'street' => 'Testweg 99',
                        'zipcode' => '54321',
                        'city' => 'Munich',
                        'firstName' => 'Jane',
                        'lastName' => 'Smith',
                    ],
                    [
                        'id' => $addressIds['address-2-shipping'],
                        'countryId' => $countryIds['country-de'],
                        'street' => 'Versandstraße 42',
                        'zipcode' => '80331',
                        'city' => 'Munich',
                        'firstName' => 'Jane',
                        'lastName' => 'Smith',
                    ],
                ],
            ],
        ];
        $this->customerRepository->create($customers, $context);
    }

    /**
     * Get the default payment method ID.
     */
    private function getDefaultPaymentMethodId(Context $context): string
    {
        // Get first available payment method from database
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $result = $this->paymentMethodRepository->searchIds($criteria, $context);

        if ($result->getTotal() > 0) {
            return $result->firstId();
        }

        // Fallback: use a known payment method UUID
        return '11491d8f829143c9a1f15c9c55e3df0c';
    }
}
