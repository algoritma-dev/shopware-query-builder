<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Scope;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;

/**
 * Interface for reusable query scopes.
 */
interface ScopeInterface
{
    /**
     * Apply the scope to the query builder.
     */
    public function apply(QueryBuilder $queryBuilder): void;
}
