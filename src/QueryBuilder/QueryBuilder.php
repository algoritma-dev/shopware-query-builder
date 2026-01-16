<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Exception\EntityNotFoundException;
use Algoritma\ShopwareQueryBuilder\Exception\InvalidAliasException;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\WhereExpression;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

/**
 * Fluent Query Builder for Shopware 6.7.
 *
 * Provides an intuitive interface for building complex queries with:
 * - Type-safe property validation
 * - Alias support for clean linear queries
 * - Integrated query execution
 * - Automatic eager loading
 */
class QueryBuilder
{
    private ?string $alias = null;

    /**
     * @var WhereExpression[]
     */
    private array $whereExpressions = [];

    /**
     * @var array<array<WhereExpression>>
     */
    private array $orWhereGroups = [];

    /**
     * @var array<string, QueryBuilder|null>
     */
    private array $associations = [];

    /**
     * @var array{field: string, direction: string}[]
     */
    private array $sortings = [];

    private ?int $limit = null;

    private ?int $offset = null;

    private ?int $page = null;

    private ?int $perPage = null;

    /**
     * @var array<string, string> Map of alias => association path
     */
    private array $aliasMap = [];

    /**
     * @var EntityRepository<EntityCollection<Entity>>|null
     */
    private ?EntityRepository $repository = null;

    private Context $context;

    public function __construct(
        private readonly string $entityClass,
        private readonly EntityDefinitionResolver $definitionResolver,
        private readonly PropertyResolver $propertyResolver,
        private readonly AssociationResolver $associationResolver,
        private readonly FilterFactory $filterFactory
    ) {
        // Validate entity class at construction
        $this->definitionResolver->getDefinition($this->entityClass);

        // Default context for execution
        $this->context = Context::createDefaultContext();
    }

    /**
     * Set alias for the main entity.
     */
    public function setAlias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Set repository for execution (used by factory).
     *
     * @internal
     *
     * @param EntityRepository<EntityCollection<Entity>> $repository
     */
    public function setRepository(EntityRepository $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Set context for execution (used by factory).
     *
     * @internal
     */
    public function setContext(Context $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add WHERE condition (AND logic).
     *
     * @param string $property Property name (supports alias, e.g., 'p.active' or nested 'manufacturer.name')
     * @param mixed $operatorOrValue Operator ('=', '>', '<', 'like', 'in', etc.) or value if operator omitted
     * @param mixed $value Value (optional if operator omitted)
     */
    public function where(string $property, mixed $operatorOrValue, mixed $value = null): self
    {
        $this->whereExpressions[] = $this->createExpression($property, $operatorOrValue, $value);

        return $this;
    }

    /**
     * Add OR WHERE group.
     */
    public function orWhere(callable $callback): self
    {
        $subQuery = new self(
            $this->entityClass,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory
        );

        // Copy alias map to sub-query
        $subQuery->aliasMap = $this->aliasMap;

        $callback($subQuery);

        $this->orWhereGroups[] = $subQuery->getWhereExpressions();

        return $this;
    }

    /**
     * Add association (eager loading) with optional alias or callback.
     *
     * @param string $association Association name (supports nested, e.g., 'manufacturer.country')
     * @param string|callable|null $aliasOrCallback Alias (e.g., 'm') or callback for sub-query (legacy)
     * @param callable|null $callback Optional callback when using alias
     */
    public function with(string $association, string|callable|null $aliasOrCallback = null, ?callable $callback = null): self
    {
        $associationInfo = $this->associationResolver->resolve($this->entityClass, $association);

        // Determine if we have an alias or callback
        $associationAlias = null;
        $actualCallback = null;

        if (is_string($aliasOrCallback)) {
            // Alias provided
            $associationAlias = $aliasOrCallback;
            $actualCallback = $callback;
        } elseif (is_callable($aliasOrCallback)) {
            // Legacy callback approach
            $actualCallback = $aliasOrCallback;
        }

        // Register alias if provided
        if ($associationAlias !== null) {
            $this->aliasMap[$associationAlias] = $associationInfo['path'];
        }

        if ($actualCallback === null) {
            // Simple eager loading without filters
            $this->associations[$associationInfo['path']] = null;
        } else {
            // Eager loading with sub-query
            $subQuery = new self(
                $associationInfo['entity'],
                $this->definitionResolver,
                $this->propertyResolver,
                $this->associationResolver,
                $this->filterFactory
            );

            // Copy alias map to sub-query for nested access
            $subQuery->aliasMap = $this->aliasMap;

            if ($associationAlias !== null) {
                $subQuery->setAlias($associationAlias);
            }

            $actualCallback($subQuery);
            $this->associations[$associationInfo['path']] = $subQuery;
        }

        return $this;
    }

    /**
     * Add sorting.
     *
     * @param string $property Property to sort by (supports alias)
     * @param string $direction 'ASC' or 'DESC' (default: 'ASC')
     */
    public function orderBy(string $property, string $direction = 'ASC'): self
    {
        $resolvedProperty = $this->resolvePropertyWithAlias($property);

        $this->sortings[] = [
            'field' => $resolvedProperty,
            'direction' => strtoupper($direction),
        ];

        return $this;
    }

    /**
     * Set limit.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set offset.
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Set pagination (calculates limit and offset automatically).
     *
     * @param int $page Page number (1-based)
     * @param int $perPage Results per page (default: 15)
     */
    public function paginate(int $page, int $perPage = 15): self
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;

        return $this;
    }

    /**
     * Convenience method for WHERE BETWEEN.
     */
    public function whereBetween(string $property, string|int $min, string|int $max): self
    {
        $resolvedProperty = $this->resolvePropertyWithAlias($property);
        $this->where($resolvedProperty, '>=', $min);
        $this->where($resolvedProperty, '<=', $max);

        return $this;
    }

    /**
     * Convenience method for WHERE IN.
     *
     * @param array<mixed> $values
     */
    public function whereIn(string $property, array $values): self
    {
        return $this->where($property, 'in', $values);
    }

    /**
     * Convenience method for WHERE NOT IN.
     *
     * @param array<mixed> $values
     */
    public function whereNotIn(string $property, array $values): self
    {
        return $this->where($property, 'not in', $values);
    }

    /**
     * Convenience method for WHERE NULL.
     */
    public function whereNull(string $property): self
    {
        return $this->where($property, 'is null');
    }

    /**
     * Convenience method for WHERE NOT NULL.
     */
    public function whereNotNull(string $property): self
    {
        return $this->where($property, 'is not null');
    }

    /**
     * Convenience method for LIKE with prefix.
     */
    public function whereStartsWith(string $property, string $value): self
    {
        return $this->where($property, 'starts with', $value);
    }

    /**
     * Convenience method for LIKE with suffix.
     */
    public function whereEndsWith(string $property, string $value): self
    {
        return $this->where($property, 'ends with', $value);
    }

    // Execution methods

    /**
     * Execute query and get EntitySearchResult.
     *
     * @throws \RuntimeException
     *
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    public function get(): EntitySearchResult
    {
        $this->ensureExecutionContext();
        $criteria = $this->toCriteria();

        return $this->repository->search($criteria, $this->context);
    }

    /**
     * Execute query and get only entities collection.
     *
     * @return EntityCollection<Entity>
     */
    public function getEntities(): EntityCollection
    {
        return $this->get()->getEntities();
    }

    /**
     * Execute query and get array of entities.
     *
     * @return Entity[]
     */
    public function toArray(): array
    {
        return $this->getEntities()->getElements();
    }

    /**
     * Execute query and get IDs.
     */
    public function getIds(): IdSearchResult
    {
        $this->ensureExecutionContext();
        $criteria = $this->toCriteria();

        return $this->repository->searchIds($criteria, $this->context);
    }

    /**
     * Execute query and get array of ID strings.
     *
     * @return string[]
     */
    public function getIdsArray(): array
    {
        return $this->getIds()->getIds();
    }

    /**
     * Get first entity or null.
     */
    public function getOneOrNull(): ?Entity
    {
        $this->limit(1);

        return $this->get()->first();
    }

    /**
     * Get first entity or throw exception.
     *
     * @throws EntityNotFoundException
     */
    public function getOneOrThrow(): Entity
    {
        $entity = $this->getOneOrNull();

        if (! $entity instanceof Entity) {
            throw new EntityNotFoundException(sprintf('No entity found for %s with given criteria', $this->getShortClassName($this->entityClass)));
        }

        return $entity;
    }

    /**
     * Alias for getOneOrNull.
     */
    public function first(): ?Entity
    {
        return $this->getOneOrNull();
    }

    /**
     * Alias for getOneOrThrow.
     *
     * @throws EntityNotFoundException
     */
    public function firstOrFail(): Entity
    {
        return $this->getOneOrThrow();
    }

    /**
     * Count results.
     */
    public function count(): int
    {
        return $this->getIds()->getTotal();
    }

    /**
     * Check if results exist.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Check if results don't exist.
     */
    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Get paginated results with metadata.
     *
     * @return array{data: EntityCollection<Entity>, total: int, page: int, perPage: int, lastPage: int, hasMorePages: bool}
     */
    public function getPaginated(): array
    {
        if ($this->page === null || $this->perPage === null) {
            throw new \RuntimeException('paginate() must be called before getPaginated()');
        }

        $result = $this->get();
        $total = $result->getTotal();
        $lastPage = (int) ceil($total / $this->perPage);

        return [
            'data' => $result->getEntities(),
            'total' => $total,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'lastPage' => $lastPage,
            'hasMorePages' => $this->page < $lastPage,
        ];
    }

    /**
     * Convert to Shopware Criteria.
     */
    public function toCriteria(): Criteria
    {
        $builder = new CriteriaBuilder($this->filterFactory);

        return $builder->build($this);
    }

    // Getters for CriteriaBuilder

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return WhereExpression[]
     */
    public function getWhereExpressions(): array
    {
        return $this->whereExpressions;
    }

    /**
     * @return array<array<WhereExpression>>
     */
    public function getOrWhereGroups(): array
    {
        return $this->orWhereGroups;
    }

    /**
     * @return array<string, QueryBuilder|null>
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }

    /**
     * @return array{field: string, direction: string}[]
     */
    public function getSortings(): array
    {
        return $this->sortings;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getPerPage(): ?int
    {
        return $this->perPage;
    }

    // Private helper methods

    /**
     * Create WHERE expression from parameters.
     */
    private function createExpression(string $property, mixed $operatorOrValue, mixed $value): WhereExpression
    {
        // Resolve property with alias support
        $resolvedProperty = $this->resolvePropertyWithAlias($property);

        // Determine operator and value
        if ($value === null && ! is_string($operatorOrValue)) {
            // where('active', true) - operator is '=', value is $operatorOrValue
            return new WhereExpression($resolvedProperty, '=', $operatorOrValue);
        }

        // where('stock', '>', 10) - explicit operator
        return new WhereExpression($resolvedProperty, (string) $operatorOrValue, $value);
    }

    /**
     * Resolve property name, handling aliases.
     *
     * @throws InvalidAliasException
     */
    private function resolvePropertyWithAlias(string $property): string
    {
        // Check if property starts with alias (e.g., 'p.active' or 'm.name')
        if (str_contains($property, '.')) {
            $parts = explode('.', $property, 2);
            $potentialAlias = $parts[0];
            $propertyPath = $parts[1];

            // Check if this is a registered alias
            if (isset($this->aliasMap[$potentialAlias])) {
                // Resolve the alias to association path
                $associationPath = $this->aliasMap[$potentialAlias];

                // For nested properties after alias, we don't need PropertyResolver validation
                // because it will be validated by Shopware at runtime
                return $associationPath . '.' . $propertyPath;
            }

            // Check if it matches the main entity alias
            if ($this->alias !== null && $potentialAlias === $this->alias) {
                // It's a reference to main entity with alias, validate the property
                return $this->propertyResolver->resolve($this->entityClass, $propertyPath);
            }

            // Not an alias, treat as nested property (e.g., 'manufacturer.name')
            return $this->propertyResolver->resolve($this->entityClass, $property);
        }

        // Simple property without dots, validate normally
        return $this->propertyResolver->resolve($this->entityClass, $property);
    }

    /**
     * Ensure repository and context are set for execution.
     *
     * @throws \RuntimeException
     */
    private function ensureExecutionContext(): void
    {
        if (! $this->repository instanceof EntityRepository) {
            throw new \RuntimeException('QueryBuilder is not configured for execution. Use the helper function query() or inject via QueryBuilderFactory.');
        }
    }

    /**
     * Get short class name for error messages.
     */
    private function getShortClassName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
