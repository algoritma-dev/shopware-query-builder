<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidParameterException;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\GroupExpression;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\WhereExpression;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * Builds Shopware Criteria from QueryBuilder.
 */
class CriteriaBuilder
{
    public function __construct(
        private readonly FilterFactory $filterFactory,
        private readonly RawExpressionParser $parser = new RawExpressionParser()
    ) {}

    /**
     * Build Criteria from QueryBuilder.
     */
    public function build(QueryBuilder $queryBuilder): Criteria
    {
        $criteria = new Criteria();

        $this->addTitle($criteria, $queryBuilder);

        // Add WHERE filters
        $this->addWhereFilters($criteria, $queryBuilder);

        // Add OR WHERE filters
        $this->addOrWhereFilters($criteria, $queryBuilder);

        // Add associations
        $this->addAssociations($criteria, $queryBuilder);

        // Add sortings
        $this->addSortings($criteria, $queryBuilder);

        // Add aggregations
        $this->addAggregations($criteria, $queryBuilder);

        // Set limit and offset
        $this->setLimitAndOffset($criteria, $queryBuilder);

        return $criteria;
    }

    /**
     * Add WHERE filters (AND logic).
     */
    private function addWhereFilters(Criteria $criteria, QueryBuilder $queryBuilder): void
    {
        $expressions = $queryBuilder->getWhereExpressions();

        if ($expressions === []) {
            return;
        }

        $parameters = $queryBuilder->getParameters();

        $filters = array_map(
            fn (WhereExpression|GroupExpression $expr): Filter => $this->convertToFilter($expr, $parameters),
            $expressions
        );

        if (count($filters) === 1) {
            $criteria->addFilter($filters[0]);
        } else {
            $criteria->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_AND, $filters)
            );
        }
    }

    /**
     * Convert expression to filter (handles both WhereExpression and GroupExpression).
     *
     * @throws InvalidParameterException
     */
    private function convertToFilter(WhereExpression|GroupExpression $expression, ?ParameterBag $parameters = null): Filter
    {
        if ($expression instanceof WhereExpression) {
            $value = $this->resolveValue($expression->getValue(), $parameters);

            return $this->filterFactory->create(
                $expression->getField(),
                $expression->getOperator(),
                $value
            );
        }

        // GroupExpression - create nested MultiFilter
        $nestedFilters = array_map(
            fn (WhereExpression|GroupExpression $expr): Filter => $this->convertToFilter($expr, $parameters),
            $expression->getExpressions()
        );

        $connection = $expression->getOperator() === 'OR'
            ? MultiFilter::CONNECTION_OR
            : MultiFilter::CONNECTION_AND;

        return new MultiFilter($connection, $nestedFilters);
    }

    /**
     * Add OR WHERE groups.
     */
    private function addOrWhereFilters(Criteria $criteria, QueryBuilder $queryBuilder): void
    {
        $orGroups = $queryBuilder->getOrWhereGroups();
        $parameters = $queryBuilder->getParameters();

        foreach ($orGroups as $group) {
            $filters = array_map(
                fn (WhereExpression|GroupExpression $expr): Filter => $this->convertExpressionToFilter($expr, $parameters),
                $group
            );

            if (count($filters) === 1) {
                $criteria->addFilter($filters[0]);
            } else {
                // Group with OR logic
                $criteria->addFilter(
                    new MultiFilter(MultiFilter::CONNECTION_OR, $filters)
                );
            }
        }
    }

    /**
     * Convert WhereExpression or GroupExpression to Filter.
     *
     * @throws InvalidParameterException
     */
    private function convertExpressionToFilter(WhereExpression|GroupExpression $expr, ?ParameterBag $parameters = null): Filter
    {
        if ($expr instanceof GroupExpression) {
            return $this->convertGroupToFilter($expr, $parameters);
        }

        $value = $this->resolveValue($expr->getValue(), $parameters);

        return $this->filterFactory->create(
            $expr->getField(),
            $expr->getOperator(),
            $value
        );
    }

    /**
     * Convert GroupExpression to MultiFilter.
     *
     * @throws InvalidParameterException
     */
    private function convertGroupToFilter(GroupExpression $group, ?ParameterBag $parameters = null): MultiFilter
    {
        $expressions = $group->getExpressions();
        $operator = $group->getOperator();

        // Recursively convert nested expressions to filters
        $filters = array_map(
            fn (WhereExpression|GroupExpression $expr): Filter => $this->convertExpressionToFilter($expr, $parameters),
            $expressions
        );

        // Determine connection type based on operator
        $connection = $operator === 'OR'
            ? MultiFilter::CONNECTION_OR
            : MultiFilter::CONNECTION_AND;

        return new MultiFilter($connection, $filters);
    }

    /**
     * Resolve parameter placeholders to actual values.
     *
     * @throws InvalidParameterException
     */
    private function resolveValue(mixed $value, ?ParameterBag $parameters = null): mixed
    {
        // Handle null or non-parameter values
        if ($value === null || ! $parameters instanceof ParameterBag) {
            return $value;
        }

        // Handle string parameter placeholder (e.g., ":status")
        if (is_string($value) && $this->parser->isParameter($value)) {
            $paramName = $this->parser->extractParameterName($value);

            return $parameters->get($paramName);
        }

        // Handle array values (for IN operator) - check for parameter placeholders
        if (is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->resolveValue($item, $parameters),
                $value
            );
        }

        return $value;
    }

    /**
     * Add associations (eager loading).
     */
    private function addAssociations(Criteria $criteria, QueryBuilder $queryBuilder): void
    {
        $associations = $queryBuilder->getAssociations();

        foreach ($associations as $path => $subQuery) {
            if ($subQuery === null) {
                // Simple association without filters
                $criteria->addAssociation($path);
            } else {
                // Association with sub-criteria
                $subCriteria = $this->build($subQuery);
                $criteria->addAssociation($path);

                // Get the association criteria and merge filters/sortings
                $associationCriteria = $criteria->getAssociation($path);

                foreach ($subCriteria->getFilters() as $filter) {
                    $associationCriteria->addFilter($filter);
                }

                foreach ($subCriteria->getSorting() as $sorting) {
                    $associationCriteria->addSorting($sorting);
                }

                if ($subCriteria->getLimit() !== null) {
                    $associationCriteria->setLimit($subCriteria->getLimit());
                }

                if ($subCriteria->getOffset() !== null) {
                    $associationCriteria->setOffset($subCriteria->getOffset());
                }

                // Merge nested associations from sub-query
                foreach ($subCriteria->getAssociations() as $nestedPath => $nestedCriteria) {
                    if ($nestedCriteria === null) {
                        $associationCriteria->addAssociation($nestedPath);
                    } else {
                        // Copy nested criteria
                        $associationCriteria->addAssociation($nestedPath);
                        $nestedAssociation = $associationCriteria->getAssociation($nestedPath);

                        foreach ($nestedCriteria->getFilters() as $filter) {
                            $nestedAssociation->addFilter($filter);
                        }

                        foreach ($nestedCriteria->getSorting() as $sorting) {
                            $nestedAssociation->addSorting($sorting);
                        }

                        if ($nestedCriteria->getLimit() !== null) {
                            $nestedAssociation->setLimit($nestedCriteria->getLimit());
                        }

                        if ($nestedCriteria->getOffset() !== null) {
                            $nestedAssociation->setOffset($nestedCriteria->getOffset());
                        }
                    }
                }
            }
        }
    }

    /**
     * Add sortings.
     */
    private function addSortings(Criteria $criteria, QueryBuilder $queryBuilder): void
    {
        $sortings = $queryBuilder->getSortings();

        foreach ($sortings as $sorting) {
            $criteria->addSorting(
                new FieldSorting(
                    $sorting['field'],
                    $sorting['direction']
                )
            );
        }
    }

    /**
     * Add aggregations.
     */
    private function addAggregations(Criteria $criteria, QueryBuilder $queryBuilder): void
    {
        $aggregations = $queryBuilder->getAggregations();

        foreach ($aggregations as $aggregation) {
            $criteria->addAggregation($aggregation);
        }
    }

    /**
     * Set limit and offset.
     */
    private function setLimitAndOffset(Criteria $criteria, QueryBuilder $queryBuilder): void
    {
        if ($queryBuilder->getLimit() !== null) {
            $criteria->setLimit($queryBuilder->getLimit());
        }

        if ($queryBuilder->getOffset() !== null) {
            $criteria->setOffset($queryBuilder->getOffset());
        }
    }

    private function addTitle(Criteria $criteria, QueryBuilder $queryBuilder): void
    {
        if ($queryBuilder->getTitle()) {
            $criteria->setTitle($queryBuilder->getTitle());
        }
    }
}
