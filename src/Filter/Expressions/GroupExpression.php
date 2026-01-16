<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Filter\Expressions;

/**
 * Represents a group of WHERE expressions with AND/OR logic.
 */
class GroupExpression
{
    /**
     * @param WhereExpression[] $expressions
     * @param string $operator 'AND' or 'OR'
     */
    public function __construct(
        private readonly array $expressions,
        private readonly string $operator = 'AND'
    ) {}

    /**
     * @return WhereExpression[]
     */
    public function getExpressions(): array
    {
        return $this->expressions;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }
}
