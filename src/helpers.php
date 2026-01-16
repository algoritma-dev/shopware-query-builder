<?php

declare(strict_types=1);

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilderFactory;

if (! function_exists('sw_query')) {
    /**
     * Create a new QueryBuilder with integrated execution support.
     *
     * This is the recommended way to create QueryBuilder instances.
     * It provides a clean, concise syntax with automatic repository and context resolution.
     *
     * @param string $entityClass Entity class (e.g., ProductEntity::class)
     * @param string|null $alias Optional alias for the entity (e.g., 'p')
     *
     * @example
     * // Simple query
     * $products = sw_query(ProductEntity::class)
     *     ->where('active', true)
     *     ->get();
     * @example
     * // With alias for clean linear queries
     * $products = sw_query(ProductEntity::class, 'p')
     *     ->with('manufacturer', 'm')
     *     ->where('p.active', true)
     *     ->where('m.active', true)
     *     ->orderBy('p.name', 'ASC')
     *     ->getEntities();
     */
    function sw_query(string $entityClass, ?string $alias = null): QueryBuilder
    {
        static $locator = null;

        if ($locator === null) {
            global $queryHelperServiceLocator;

            if ($queryHelperServiceLocator === null) {
                throw new RuntimeException('Service locator is not available. The query() helper can only be used after the container is initialized. Consider injecting QueryBuilderFactory via dependency injection instead.');
            }

            $locator = $queryHelperServiceLocator;
        }

        if (! method_exists($locator, 'get')) {
            throw new RuntimeException('Service locator does not have a get method.');
        }

        /** @var QueryBuilderFactory $factory */
        $factory = $locator->get('query_factory');

        return $factory->create($entityClass, $alias);
    }
}
