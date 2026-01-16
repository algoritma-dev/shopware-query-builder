<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Filter\Expressions;

/**
 * Represents a WHERE condition expression.
 */
class WhereExpression
{
    public function __construct(
        private readonly string $field,
        private readonly string $operator,
        private readonly mixed $value
    ) {}

    public function getField(): string
    {
        return $this->field;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
