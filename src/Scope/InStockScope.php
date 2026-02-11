<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Scope;

use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;

/**
 * Scope for filtering entities in stock.
 */
class InStockScope implements ScopeInterface
{
    public function __construct(
        private readonly int $minStock = 1
    ) {}

    public function apply(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->where("stock >= {$this->minStock}");
    }
}
