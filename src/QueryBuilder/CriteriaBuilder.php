<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\QueryBuilder;

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
        private readonly FilterFactory $filterFactory
    ) {}

    /**
     * Build Criteria from QueryBuilder.
     */
    public function build(QueryBuilder $queryBuilder): Criteria
    {
        $criteria = new Criteria();

        // Add WHERE filters
        $this->addWhereFilters($criteria, $queryBuilder);

        // Add OR WHERE filters
        $this->addOrWhereFilters($criteria, $queryBuilder);

        // Add associations
        $this->addAssociations($criteria, $queryBuilder);

        // Add sortings
        $this->addSortings($criteria, $queryBuilder);

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

        $filters = array_map(
            fn (WhereExpression $expr): Filter => $this->filterFactory->create(
                $expr->getField(),
                $expr->getOperator(),
                $expr->getValue()
            ),
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
     * Add OR WHERE groups.
     */
    private function addOrWhereFilters(Criteria $criteria, QueryBuilder $queryBuilder): void
    {
        $orGroups = $queryBuilder->getOrWhereGroups();

        foreach ($orGroups as $group) {
            $filters = array_map(
                fn (WhereExpression $expr): Filter => $this->filterFactory->create(
                    $expr->getField(),
                    $expr->getOperator(),
                    $expr->getValue()
                ),
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
}
