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
        private readonly mixed $value,
        private readonly ?string $rawExpression = null
    ) {}

    /**
     * Create WhereExpression from raw SQL-like expression.
     */
    public static function fromRaw(string $expression, RawExpressionParser $parser): self
    {
        $parsed = $parser->parse($expression);

        if ($parsed['isCompound']) {
            throw new \InvalidArgumentException('Cannot create single WhereExpression from compound expression. Use auto-grouping instead.');
        }

        $condition = $parsed['conditions'][0];

        return new self(
            $condition['field'],
            $condition['operator'],
            $condition['value'],
            $condition['raw']
        );
    }

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

    public function getRawExpression(): ?string
    {
        return $this->rawExpression;
    }
}
