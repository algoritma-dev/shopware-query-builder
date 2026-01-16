<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Scope;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;

/**
 * Scope for filtering active entities.
 */
class ActiveScope implements ScopeInterface
{
    public function apply(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->where('active', true);
    }
}
