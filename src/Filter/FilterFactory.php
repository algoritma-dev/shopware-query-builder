<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Filter;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidOperatorException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;

/**
 * Creates Shopware Filter objects from expressions.
 */
class FilterFactory
{
    private readonly OperatorMapper $operatorMapper;

    public function __construct()
    {
        $this->operatorMapper = new OperatorMapper();
    }

    /**
     * Create a Filter from field, operator, and value.
     *
     * @throws InvalidOperatorException
     */
    public function create(string $field, string $operator, mixed $value): Filter
    {
        $filterType = $this->operatorMapper->getFilterType($operator);

        return match ($filterType) {
            'equals' => new EqualsFilter($field, $value),
            'not_equals' => new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter($field, $value)]
            ),
            'range' => $this->createRangeFilter($field, $operator, $value),
            'contains' => new ContainsFilter($field, (string) $value),
            'in' => new EqualsAnyFilter($field, (array) $value),
            'not_in' => new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsAnyFilter($field, (array) $value)]
            ),
            'null' => new EqualsFilter($field, null),
            'not_null' => new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter($field, null)]
            ),
            'prefix' => new PrefixFilter($field, (string) $value),
            'suffix' => new SuffixFilter($field, (string) $value),
            default => throw new InvalidOperatorException(sprintf("Operator '%s' not supported", $operator)),
        };
    }

    /**
     * Create a RangeFilter based on the operator.
     */
    private function createRangeFilter(string $field, string $operator, mixed $value): RangeFilter
    {
        $parameters = match ($operator) {
            '>', 'gt' => [RangeFilter::GT => $value],
            '>=', 'gte' => [RangeFilter::GTE => $value],
            '<', 'lt' => [RangeFilter::LT => $value],
            '<=', 'lte' => [RangeFilter::LTE => $value],
            default => throw new InvalidOperatorException(sprintf("Invalid range operator: '%s'", $operator)),
        };

        return new RangeFilter($field, $parameters);
    }
}
