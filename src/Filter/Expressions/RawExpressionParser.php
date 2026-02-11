<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Filter\Expressions;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidOperatorException;

/**
 * Parses raw SQL-like WHERE expressions into structured data.
 *
 * Supports:
 * - Simple expressions: 'stock > 10'
 * - Compound AND: 'stock > 10 AND active = true'
 * - Compound OR: 'featured = true OR promoted = true'
 * - Operators: =, !=, <>, >, >=, <, <=, LIKE, IN, NOT IN, IS NULL, IS NOT NULL
 * - Value types: strings (quoted), numbers, booleans, null, arrays
 * - Named parameters: :paramName (e.g., 'status = :status')
 */
class RawExpressionParser
{
    /**
     * Pattern to match parameter placeholders.
     */
    private const PARAMETER_PATTERN = '/^:([a-zA-Z_]\w*)$/';

    /**
     * Supported comparison operators.
     *
     * @var array<string>
     */
    private const OPERATORS = [
        '!=',
        '<>',
        '>=',
        '<=',
        '>',
        '<',
        '=',
    ];

    /**
     * Multi-word operators.
     *
     * @var array<string>
     */
    private const MULTI_WORD_OPERATORS = [
        'NOT IN',
        'IS NOT NULL',
        'IS NULL',
        'LIKE',
        'IN',
    ];

    /**
     * Logical operators.
     *
     * @var array<string>
     */
    private const LOGICAL_OPERATORS = ['AND', 'OR'];

    /**
     * Check if a value token is a parameter placeholder.
     */
    public function isParameter(string $token): bool
    {
        return preg_match(self::PARAMETER_PATTERN, trim($token)) === 1;
    }

    /**
     * Extract parameter name from placeholder (removes ':' prefix).
     */
    public function extractParameterName(string $token): string
    {
        $token = trim($token);
        if (preg_match(self::PARAMETER_PATTERN, $token, $matches)) {
            return $matches[1];
        }

        throw new \InvalidArgumentException(sprintf("'%s' is not a valid parameter placeholder", $token));
    }

    /**
     * Parse a raw WHERE expression.
     *
     * @return array{isCompound: bool, conditions: array<int, array{field: string, operator: string, value: mixed, raw: string}>, operator: string|null}
     */
    public function parse(string $expression): array
    {
        $expression = trim($expression);

        // Check if compound expression (contains AND or OR at top level)
        $logicalOperator = $this->detectLogicalOperator($expression);

        if ($logicalOperator !== null) {
            // Compound expression
            $conditions = $this->parseCompoundExpression($expression, $logicalOperator);

            return [
                'isCompound' => true,
                'conditions' => $conditions,
                'operator' => $logicalOperator,
            ];
        }

        // Simple expression
        $condition = $this->parseSimpleExpression($expression);

        return [
            'isCompound' => false,
            'conditions' => [$condition],
            'operator' => null,
        ];
    }

    /**
     * Check if expression is compound (contains AND/OR).
     */
    public function isCompoundExpression(string $expression): bool
    {
        return $this->detectLogicalOperator($expression) !== null;
    }

    /**
     * Detect logical operator at the top level (not inside quotes or parentheses).
     */
    private function detectLogicalOperator(string $expression): ?string
    {
        $upperExpression = strtoupper($expression);

        foreach (self::LOGICAL_OPERATORS as $operator) {
            // Look for operator surrounded by spaces
            if (str_contains($upperExpression, " {$operator} ")) {
                return $operator;
            }
        }

        return null;
    }

    /**
     * Parse compound expression (with AND or OR).
     *
     * @return array<int, array{field: string, operator: string, value: mixed, raw: string}>
     */
    private function parseCompoundExpression(string $expression, string $logicalOperator): array
    {
        // Split by logical operator (case-insensitive)
        $pattern = '/\s+' . preg_quote($logicalOperator, '/') . '\s+/i';
        $parts = preg_split($pattern, $expression);

        if ($parts === false) {
            throw new \RuntimeException('Failed to split compound expression');
        }

        $conditions = [];

        foreach ($parts as $part) {
            $conditions[] = $this->parseSimpleExpression(trim($part));
        }

        return $conditions;
    }

    /**
     * Parse simple expression (field operator value).
     *
     * @return array{field: string, operator: string, value: mixed, raw: string}
     */
    private function parseSimpleExpression(string $expression): array
    {
        $originalExpression = $expression;

        // Try multi-word operators first
        foreach (self::MULTI_WORD_OPERATORS as $operator) {
            $pos = stripos($expression, " {$operator}");

            if ($pos !== false) {
                $field = trim(substr($expression, 0, $pos));
                $valueStart = $pos + strlen($operator) + 1;
                $value = $valueStart < strlen($expression) ? trim(substr($expression, $valueStart)) : null;

                // Handle IS NULL and IS NOT NULL (no value)
                $value = stripos($operator, 'NULL') !== false ? null : $this->extractValue($value);

                return [
                    'field' => $field,
                    'operator' => strtolower($operator),
                    'value' => $value,
                    'raw' => $originalExpression,
                ];
            }
        }

        // Try single/double character operators
        foreach (self::OPERATORS as $operator) {
            $pos = strpos($expression, $operator);

            if ($pos !== false) {
                $field = trim(substr($expression, 0, $pos));
                $value = trim(substr($expression, $pos + strlen($operator)));

                return [
                    'field' => $field,
                    'operator' => $operator,
                    'value' => $this->extractValue($value),
                    'raw' => $originalExpression,
                ];
            }
        }

        throw new InvalidOperatorException(sprintf("Could not parse expression: '%s'. No valid operator found.", $expression));
    }

    /**
     * Extract and convert value from string token.
     *
     * Returns the token as-is if it's a parameter placeholder (e.g., :status),
     * allowing the CriteriaBuilder to replace it with the actual value later.
     */
    private function extractValue(?string $valueToken): mixed
    {
        if ($valueToken === null || $valueToken === '') {
            return null;
        }

        $valueToken = trim($valueToken);

        // Handle NULL keyword
        if (strtoupper($valueToken) === 'NULL') {
            return null;
        }

        // Handle boolean keywords
        if (strtolower($valueToken) === 'true') {
            return true;
        }

        if (strtolower($valueToken) === 'false') {
            return false;
        }

        // Handle quoted strings (single or double quotes)
        if (preg_match('/^(["\'])(.*)\1$/', $valueToken, $matches)) {
            return $matches[2]; // Return unquoted value
        }

        // Handle arrays for IN operator - format: (1,2,3) or (a,b,c) or (:param)
        if (preg_match('/^\((.*)\)$/', $valueToken, $matches)) {
            // Check if it's a single parameter placeholder for array value
            $innerContent = trim($matches[1]);
            if ($this->isParameter($innerContent)) {
                return $innerContent; // Return parameter placeholder for array
            }

            $items = array_map(trim(...), explode(',', $matches[1]));

            return array_map($this->extractValue(...), $items);
        }

        // Handle numbers (int or float)
        if (is_numeric($valueToken)) {
            return str_contains($valueToken, '.') ? (float) $valueToken : (int) $valueToken;
        }

        // Unquoted string - return as-is
        return $valueToken;
    }
}
