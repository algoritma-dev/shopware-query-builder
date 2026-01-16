<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Filter;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidOperatorException;

/**
 * Maps user-friendly operators to filter types.
 */
class OperatorMapper
{
    /**
     * @var array<string, string>
     */
    private const array OPERATOR_MAP = [
        '=' => 'equals',
        '==' => 'equals',
        '!=' => 'not_equals',
        '<>' => 'not_equals',
        '>' => 'range',
        'gt' => 'range',
        '>=' => 'range',
        'gte' => 'range',
        '<' => 'range',
        'lt' => 'range',
        '<=' => 'range',
        'lte' => 'range',
        'LIKE' => 'contains',
        'like' => 'contains',
        'IN' => 'in',
        'in' => 'in',
        'NOT IN' => 'not_in',
        'not in' => 'not_in',
        'IS NULL' => 'null',
        'is null' => 'null',
        'IS NOT NULL' => 'not_null',
        'is not null' => 'not_null',
        'STARTS WITH' => 'prefix',
        'starts with' => 'prefix',
        'ENDS WITH' => 'suffix',
        'ends with' => 'suffix',
    ];

    /**
     * Get the filter type for an operator.
     *
     * @throws InvalidOperatorException
     */
    public function getFilterType(string $operator): string
    {
        if (! isset(self::OPERATOR_MAP[$operator])) {
            throw new InvalidOperatorException(sprintf("Unknown operator: '%s'. Supported operators: %s", $operator, implode(', ', array_keys(self::OPERATOR_MAP))));
        }

        return self::OPERATOR_MAP[$operator];
    }

    /**
     * Check if an operator is valid.
     */
    public function isValidOperator(string $operator): bool
    {
        return isset(self::OPERATOR_MAP[$operator]);
    }

    /**
     * Get all supported operators.
     *
     * @return string[]
     */
    public function getSupportedOperators(): array
    {
        return array_keys(self::OPERATOR_MAP);
    }
}
