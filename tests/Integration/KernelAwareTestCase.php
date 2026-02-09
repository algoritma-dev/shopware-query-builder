<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Integration;

use Algoritma\ShopwareQueryBuilder\Tests\DatabaseHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Base class for integration tests that require database access.
 *
 * Extends KernelTestBehaviour to automatically boot the kernel and manage database transactions.
 */
abstract class KernelAwareTestCase extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    protected Context $context;

    protected DatabaseHelper $databaseHelper;

    protected function setUp(): void
    {
        // Boot the kernel
        //        self::getKernel();
        // Initialize context
        $this->context = Context::createCLIContext();
        // Set up database helper
        $this->initializeDatabaseHelper();
        // Load fixtures
        $this->databaseHelper->loadFixtures($this->context);
    }

    protected function tearDown(): void
    {
        // Clear fixtures after each test
        try {
            $this->databaseHelper->clearFixtures($this->context);
        } catch (\Throwable) {
            // Silently ignore errors during teardown
        }
    }

    /**
     * Get a service from the container.
     */
    protected function getService(string $id): mixed
    {
        return self::getContainer()->get($id);
    }

    /**
     * Get a repository instance by entity class.
     */
    protected function getRepository(string $entityClass): EntityRepository
    {
        // Map entity classes to repository service IDs
        $repositoryMap = [
            ProductEntity::class => 'product.repository',
            CategoryEntity::class => 'category.repository',
            'Shopware\Core\Content\Manufacturer\ManufacturerEntity' => 'product_manufacturer.repository',
        ];
        $serviceId = $repositoryMap[$entityClass] ?? null;
        if (! $serviceId) {
            throw new \InvalidArgumentException("No repository mapping found for entity: {$entityClass}");
        }

        return $this->getService($serviceId);
    }

    /**
     * Initialize the database helper with repositories.
     */
    private function initializeDatabaseHelper(): void
    {
        $container = self::getContainer();
        $this->databaseHelper = new DatabaseHelper(
            $container->get('product.repository'),
            $container->get('category.repository'),
            $container->get('product_manufacturer.repository'),
        );
    }
}
