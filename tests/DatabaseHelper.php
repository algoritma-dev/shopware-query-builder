<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Helper class for managing test database and fixtures.
 */
class DatabaseHelper
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $manufacturerRepository,
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
    }

    /**
     * Clear all test data from the database.
     */
    public function clearFixtures(Context $context): void
    {
        // Delete products
        try {
            $products = $this->productRepository->searchIds(null, $context);
            if (! $products->getIds()) {
                return;
            }
            $deleteData = array_map(fn ($id): array => ['id' => $id], $products->getIds());
            $this->productRepository->delete($deleteData, $context);
        } catch (\Throwable) {
            // Silently ignore errors during cleanup
        }
        // Delete categories
        try {
            $categories = $this->categoryRepository->searchIds(null, $context);
            if (! $categories->getIds()) {
                return;
            }
            $deleteData = array_map(fn ($id): array => ['id' => $id], $categories->getIds());
            $this->categoryRepository->delete($deleteData, $context);
        } catch (\Throwable) {
            // Silently ignore errors during cleanup
        }
        // Delete manufacturers
        try {
            $manufacturers = $this->manufacturerRepository->searchIds(null, $context);
            if (! $manufacturers->getIds()) {
                return;
            }
            $deleteData = array_map(fn ($id): array => ['id' => $id], $manufacturers->getIds());
            $this->manufacturerRepository->delete($deleteData, $context);
        } catch (\Throwable) {
            // Silently ignore errors during cleanup
        }
    }
}
