<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Exception\EntityNotFoundException;
use Algoritma\ShopwareQueryBuilder\Exception\InsertEntityException;
use Algoritma\ShopwareQueryBuilder\Exception\InvalidAliasException;
use Algoritma\ShopwareQueryBuilder\Exception\InvalidParameterException;
use Algoritma\ShopwareQueryBuilder\Exception\UpdateEntityException;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\GroupExpression;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\WhereExpression;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Algoritma\ShopwareQueryBuilder\Scope\ScopeInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

use function count;
use function dump;

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
     * @var array<WhereExpression|GroupExpression>
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
     * @var array<string, CountAggregation|SumAggregation|AvgAggregation|MinAggregation|MaxAggregation>
     */
    private array $aggregations = [];

    private bool $withTrashed = false;

    private bool $onlyTrashed = false;

    /**
     * @var EntityRepository<EntityCollection<Entity>>|null
     */
    private ?EntityRepository $repository = null;

    private Context $context;

    private readonly ParameterBag $parameters;

    private ?string $title = null;

    public function __construct(
        private readonly string $entityClass,
        private readonly EntityDefinitionResolver $definitionResolver,
        private readonly PropertyResolver $propertyResolver,
        private readonly AssociationResolver $associationResolver,
        private readonly FilterFactory $filterFactory,
        private readonly RawExpressionParser $parser
    ) {
        // Validate entity class at construction
        $this->definitionResolver->getDefinition($this->entityClass);

        // Default context for execution
        $this->context = Context::createCLIContext();

        // Initialize parameter bag
        $this->parameters = new ParameterBag();
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
     * Set a named parameter for use in queries.
     *
     * @param string $name Parameter name (with or without ':' prefix)
     * @param mixed $value Parameter value
     *
     * @throws InvalidParameterException
     */
    public function setParameter(string $name, mixed $value): self
    {
        $this->parameters->set($name, $value);

        return $this;
    }

    /**
     * Set multiple parameters at once.
     *
     * @param array<string, mixed> $parameters Map of parameter names to values
     *
     * @throws InvalidParameterException
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters->setAll($parameters);

        return $this;
    }

    /**
     * Get the parameter bag.
     *
     * @internal Used by CriteriaBuilder
     */
    public function getParameters(): ParameterBag
    {
        return $this->parameters;
    }

    /**
     * Add WHERE condition using raw SQL-like expression or closure.
     *
     * Accepts two forms:
     * 1. String expression:
     *    - Simple: 'stock > 10'
     *    - Compound AND: 'stock > 10 AND active = true' (auto-creates GroupExpression)
     *    - Compound OR: 'featured = true OR promoted = true' (auto-creates GroupExpression)
     *
     * 2. Closure for grouping (conditions inside are joined with AND by default):
     *    - where(function($q) { $q->where('a')->where('b'); })  // (a AND b)
     *    - where(function($q) { $q->where('a')->orWhere('b'); }) // (a OR b)
     *
     * @param string|callable $expression Raw SQL-like expression or closure
     */
    public function where(string|callable $expression): self
    {
        // Handle closure for grouped conditions
        if (is_callable($expression)) {
            /** @phpstan-ignore method.deprecated */
            return $this->whereGroup($expression, 'AND');
        }

        $parsed = $this->parser->parse($expression);

        if ($parsed['isCompound']) {
            // Auto-create GroupExpression for AND/OR
            return $this->autoCreateGroup($parsed);
        }

        // Simple expression - resolve field with alias before creating expression
        $condition = $parsed['conditions'][0];
        $resolvedField = $this->resolvePropertyWithAlias($condition['field']);

        $whereExpr = new WhereExpression(
            $resolvedField,
            $condition['operator'],
            $condition['value'],
            $condition['raw']
        );
        $this->whereExpressions[] = $whereExpr;

        return $this;
    }

    /**
     * Alias for where() - for semantic clarity when using raw expressions.
     */
    public function whereRaw(string $expression): self
    {
        return $this->where($expression);
    }

    /**
     * Add OR WHERE condition using raw SQL-like expression or closure.
     *
     * Accepts two forms:
     * 1. String expression:
     *    - Simple: 'stock > 10' (joined with OR)
     *    - Compound: 'stock > 10 AND active = true' (auto-creates GroupExpression, joined with OR)
     *
     * 2. Closure for OR grouping (conditions inside are joined with AND by default):
     *    - orWhere(function($q) { $q->where('a')->where('b'); })  // OR (a AND b)
     *    - orWhere(function($q) { $q->where('a')->orWhere('b'); }) // OR (a OR b)
     *
     * @param string|callable $expression Raw SQL-like expression or closure
     */
    public function orWhere(string|callable $expression): self
    {
        // Handle closure for OR grouped conditions
        if (is_callable($expression)) {
            $subQuery = new self(
                $this->entityClass,
                $this->definitionResolver,
                $this->propertyResolver,
                $this->associationResolver,
                $this->filterFactory,
                $this->parser
            );

            // Copy alias map to sub-query
            $subQuery->aliasMap = $this->aliasMap;

            $expression($subQuery);

            $this->orWhereGroups[] = $subQuery->getWhereExpressions();

            return $this;
        }

        // Handle string expression - add to OR groups as single expression
        $parsed = $this->parser->parse($expression);

        if ($parsed['isCompound']) {
            // Create GroupExpression for compound expression and add to OR groups
            $expressions = [];
            foreach ($parsed['conditions'] as $condition) {
                $resolvedField = $this->resolvePropertyWithAlias($condition['field']);
                $expressions[] = new WhereExpression(
                    $resolvedField,
                    $condition['operator'],
                    $condition['value'],
                    $condition['raw']
                );
            }
            $this->orWhereGroups[] = [
                new GroupExpression($expressions, $parsed['operator']),
            ];
        } else {
            // Simple expression - add as single OR condition
            $whereExpr = WhereExpression::fromRaw($expression, $this->parser);
            $this->orWhereGroups[] = [$whereExpr];
        }

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
        // Check if the association starts with an alias (e.g., 'addressAlias.country')
        $resolvedAssociation = $association;
        if (str_contains($association, '.')) {
            $parts = explode('.', $association, 2);
            $potentialAlias = $parts[0];

            // If the first part is a known alias, resolve it
            if (isset($this->aliasMap[$potentialAlias])) {
                $resolvedAssociation = $this->aliasMap[$potentialAlias] . '.' . $parts[1];
            }
        }

        $associationInfo = $this->associationResolver->resolve($this->entityClass, $resolvedAssociation);

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
                $this->filterFactory,
                $this->parser
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
        $this->where("{$property} >= {$min}");
        $this->where("{$property} <= {$max}");

        return $this;
    }

    /**
     * Convenience method for WHERE IN.
     *
     * @param array<mixed> $values
     */
    public function whereIn(string $property, array $values): self
    {
        $valueList = implode(',', array_map(fn (mixed $v): mixed => is_string($v) ? "\"{$v}\"" : $v, $values));

        return $this->where("{$property} IN ({$valueList})");
    }

    /**
     * Convenience method for WHERE NOT IN.
     *
     * @param array<mixed> $values
     */
    public function whereNotIn(string $property, array $values): self
    {
        $valueList = implode(',', array_map(fn (mixed $v): mixed => is_string($v) ? "\"{$v}\"" : $v, $values));

        return $this->where("{$property} NOT IN ({$valueList})");
    }

    /**
     * Convenience method for WHERE NULL.
     */
    public function whereNull(string $property): self
    {
        return $this->where("{$property} IS NULL");
    }

    /**
     * Convenience method for WHERE NOT NULL.
     */
    public function whereNotNull(string $property): self
    {
        return $this->where("{$property} IS NOT NULL");
    }

    /**
     * Convenience method for LIKE with prefix.
     */
    public function whereStartsWith(string $property, string $value): self
    {
        return $this->where("{$property} LIKE \"{$value}%\"");
    }

    /**
     * Convenience method for LIKE with suffix.
     */
    public function whereEndsWith(string $property, string $value): self
    {
        return $this->where("{$property} LIKE \"%{$value}\"");
    }

    // Aggregation methods

    /**
     * Add COUNT aggregation.
     *
     * @param string $field Field to count (default: 'id')
     * @param string $name Aggregation name (default: 'count')
     */
    public function addCount(string $field = 'id', string $name = 'count'): self
    {
        $resolvedField = $this->resolvePropertyWithAlias($field);
        $this->aggregations[$name] = new CountAggregation($name, $resolvedField);

        return $this;
    }

    /**
     * Add SUM aggregation.
     *
     * @param string $field Field to sum
     * @param string $name Aggregation name (default: 'sum')
     */
    public function addSum(string $field, string $name = 'sum'): self
    {
        $resolvedField = $this->resolvePropertyWithAlias($field);
        $this->aggregations[$name] = new SumAggregation($name, $resolvedField);

        return $this;
    }

    /**
     * Add AVG aggregation.
     *
     * @param string $field Field to average
     * @param string $name Aggregation name (default: 'avg')
     */
    public function addAvg(string $field, string $name = 'avg'): self
    {
        $resolvedField = $this->resolvePropertyWithAlias($field);
        $this->aggregations[$name] = new AvgAggregation($name, $resolvedField);

        return $this;
    }

    /**
     * Add MIN aggregation.
     *
     * @param string $field Field to find minimum
     * @param string $name Aggregation name (default: 'min')
     */
    public function addMin(string $field, string $name = 'min'): self
    {
        $resolvedField = $this->resolvePropertyWithAlias($field);
        $this->aggregations[$name] = new MinAggregation($name, $resolvedField);

        return $this;
    }

    /**
     * Add MAX aggregation.
     *
     * @param string $field Field to find maximum
     * @param string $name Aggregation name (default: 'max')
     */
    public function addMax(string $field, string $name = 'max'): self
    {
        $resolvedField = $this->resolvePropertyWithAlias($field);
        $this->aggregations[$name] = new MaxAggregation($name, $resolvedField);

        return $this;
    }

    // Grouping methods

    /**
     * Add grouped WHERE conditions (AND logic by default).
     *
     * @deprecated Use where(callable) or orWhere(callable) instead
     *
     * @param callable $callback Callback that receives a sub-query builder
     * @param string $operator 'AND' or 'OR' (default: 'AND')
     */
    public function whereGroup(callable $callback, string $operator = 'AND'): self
    {
        $subQuery = new self(
            $this->entityClass,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory,
            $this->parser
        );

        // Copy alias map to sub-query
        $subQuery->aliasMap = $this->aliasMap;

        $callback($subQuery);

        $this->whereExpressions[] = new GroupExpression(
            $subQuery->getWhereExpressions(),
            strtoupper($operator)
        );

        return $this;
    }

    /**
     * Add grouped WHERE conditions with OR logic.
     *
     * @deprecated Use orWhere(callable) instead
     *
     * @param callable $callback Callback that receives a sub-query builder
     */
    public function orWhereGroup(callable $callback): self
    {
        return $this->whereGroup($callback, 'OR');
    }

    // Scope methods

    /**
     * Apply a scope to the query.
     */
    public function scope(ScopeInterface $scope): self
    {
        $scope->apply($this);

        return $this;
    }

    /**
     * Apply multiple scopes to the query.
     *
     * @param ScopeInterface[] $scopes
     */
    public function scopes(array $scopes): self
    {
        foreach ($scopes as $scope) {
            $this->scope($scope);
        }

        return $this;
    }

    // Soft Deletes methods

    /**
     * Include soft-deleted entities in results.
     */
    public function withTrashed(): self
    {
        $this->withTrashed = true;

        return $this;
    }

    /**
     * Return only soft-deleted entities.
     */
    public function onlyTrashed(): self
    {
        $this->onlyTrashed = true;

        return $this;
    }

    /**
     * Exclude soft-deleted entities (default behavior).
     */
    public function withoutTrashed(): self
    {
        $this->withTrashed = false;
        $this->onlyTrashed = false;

        return $this;
    }

    // Debugging methods

    /**
     * Enable debug mode (prints query info on execution).
     */
    public function debug(): self
    {
        return $this;
    }

    /**
     * Dump query information and continue execution.
     */
    public function dump(): self
    {
        $this->dumpQueryInfo();

        return $this;
    }

    /**
     * Dump query information and die.
     */
    public function dd(): never
    {
        $this->dumpQueryInfo();
        exit(1);
    }

    /**
     * Export query as array (for debugging/inspection).
     *
     * @return array{entity: string, alias: string|null, where: array<int, array<string, mixed>>, orWhere: array<int, array<int, array<string, mixed>>>, with: array<int, string>, orderBy: array<int, array{field: string, direction: string}>, limit: int|null, offset: int|null, aggregations: array<int, string>, withTrashed: bool, onlyTrashed: bool}
     */
    public function toDebugArray(): array
    {
        return [
            'entity' => $this->entityClass,
            'alias' => $this->alias,
            'where' => $this->formatExpressionsForDebug($this->whereExpressions),
            'orWhere' => \array_map(
                $this->formatExpressionsForDebug(...),
                $this->orWhereGroups
            ),
            'with' => array_keys($this->associations),
            'orderBy' => $this->sortings,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'aggregations' => array_keys($this->aggregations),
            'withTrashed' => $this->withTrashed,
            'onlyTrashed' => $this->onlyTrashed,
        ];
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
        // Apply soft delete filters automatically
        $this->applySoftDeleteFilters();

        return (new CriteriaBuilder($this->filterFactory))->build($this);
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

    /**
     * @return array<string, CountAggregation|SumAggregation|AvgAggregation|MinAggregation|MaxAggregation>
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    /**
     * @param array<array<string, mixed|null>>|array<string, mixed|null> $data
     *
     * @throws UpdateEntityException
     *
     * @return Entity|EntityCollection<Entity>
     */
    public function update(array $data): Entity|EntityCollection
    {
        $this->ensureExecutionContext();

        if ($this->whereExpressions !== [] || $this->orWhereGroups !== []) {
            $this->ensureDataIsValidForConditionUse($data);

            $entitiesIds = $this->getIds()->getIds();

            $data = \array_map(static fn (string $id): array => \array_merge($data, ['id' => $id]), $entitiesIds);
        }

        $event = $this->repository->update($data, $this->context);

        if (\count($event->getErrors()) > 0) {
            throw new UpdateEntityException($event->getErrors());
        }

        $primaryKeys = $event->getPrimaryKeys($this->repository->getDefinition()->getEntityName());

        $entities = $this->repository->search(new Criteria($primaryKeys), $this->context)->getEntities();

        if ($entities->count() === 1) {
            return $entities->first();
        }

        return $entities;
    }

    /**
     * @param array<array<string, mixed|null>>|array<string, mixed|null> $data
     *
     * @throws UpdateEntityException
     *
     * @return Entity|EntityCollection<Entity>
     */
    public function insert(array $data): Entity|EntityCollection
    {
        $this->ensureExecutionContext();

        $event = $this->repository->create($data, $this->context);

        if (\count($event->getErrors()) > 0) {
            throw new InsertEntityException($event->getErrors());
        }

        $primaryKeys = $event->getPrimaryKeys($this->repository->getDefinition()->getEntityName());

        $entities = $this->repository->search(new Criteria($primaryKeys), $this->context)->getEntities();

        if ($entities->count() === 1) {
            return $entities->first();
        }

        return $entities;
    }

    public function title(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Format expressions for debug output.
     *
     * @param array<WhereExpression|GroupExpression> $expressions
     *
     * @return array<int, array{field?: string, operator?: string, value?: mixed, type?: string, group?: array<int, array<string, mixed>>}>
     */
    private function formatExpressionsForDebug(array $expressions): array
    {
        return \array_map(function (GroupExpression|WhereExpression $expr): array {
            if ($expr instanceof GroupExpression) {
                return [
                    'type' => 'group',
                    'operator' => $expr->getOperator(),
                    'group' => $this->formatExpressionsForDebug($expr->getExpressions()),
                ];
            }

            return [
                'field' => $expr->getField(),
                'operator' => $expr->getOperator(),
                'value' => $expr->getValue(),
            ];
        }, $expressions);
    }

    /**
     * Print query information to output.
     */
    private function dumpQueryInfo(): void
    {
        $data = $this->toDebugArray();

        echo "\n=== Query Builder Debug ===\n";
        echo "Entity: {$data['entity']}\n";

        if ($data['alias'] !== null) {
            echo "Alias: {$data['alias']}\n";
        }

        if ($data['where'] !== []) {
            echo "\nWHERE Conditions:\n";
            print_r($data['where']);
        }

        if ($data['orWhere'] !== []) {
            echo "\nOR WHERE Groups:\n";
            print_r($data['orWhere']);
        }

        if ($data['with'] !== []) {
            echo "\nAssociations: " . implode(', ', $data['with']) . "\n";
        }

        if ($data['orderBy'] !== []) {
            echo "\nOrder By:\n";
            foreach ($data['orderBy'] as $sorting) {
                echo "  - {$sorting['field']} {$sorting['direction']}\n";
            }
        }

        if ($data['aggregations'] !== []) {
            echo "\nAggregations: " . implode(', ', $data['aggregations']) . "\n";
        }

        if ($data['limit'] !== null) {
            echo "\nLimit: {$data['limit']}\n";
        }

        if ($data['offset'] !== null) {
            echo "Offset: {$data['offset']}\n";
        }

        if ($data['withTrashed']) {
            echo "\nWith Trashed: Yes\n";
        }

        if ($data['onlyTrashed']) {
            echo "Only Trashed: Yes\n";
        }

        echo "===========================\n\n";
    }

    /**
     * Apply soft delete filters based on flags.
     */
    private function applySoftDeleteFilters(): void
    {
        // Only apply soft delete filters if the entity has the deletedAt field
        if (! $this->definitionResolver->hasField($this->entityClass, 'deletedAt')) {
            return;
        }

        if ($this->onlyTrashed) {
            $this->whereNotNull('deletedAt');
        } elseif (! $this->withTrashed) {
            $this->whereNull('deletedAt');
        }
    }

    // Private helper methods

    /**
     * Auto-create GroupExpression from compound parsed expression.
     *
     * @param array{isCompound: bool, conditions: array<int, array{field: string, operator: string, value: mixed, raw: string}>, operator: string|null} $parsed
     */
    private function autoCreateGroup(array $parsed): self
    {
        $expressions = [];

        foreach ($parsed['conditions'] as $condition) {
            // Resolve property with alias
            $resolvedField = $this->resolvePropertyWithAlias($condition['field']);

            $expressions[] = new WhereExpression(
                $resolvedField,
                $condition['operator'],
                $condition['value'],
                $condition['raw']
            );
        }

        $this->whereExpressions[] = new GroupExpression(
            $expressions,
            $parsed['operator'] // 'AND' or 'OR'
        );

        return $this;
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

    /**
     * @param array<mixed> $data
     *
     * @throws UpdateEntityException
     */
    private function ensureDataIsValidForConditionUse(array $data): void
    {
        if (($this->whereExpressions !== [] || $this->orWhereGroups !== []) && (\array_is_list($data) || $this->hasNonStringKeys($data) || isset($data['id']))) {
            throw new UpdateEntityException(['Data for update with conditions must be an associative array without "id" field.']);
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function hasNonStringKeys(array $data): bool
    {
        foreach (array_keys($data) as $key) {
            if (! \is_string($key)) {
                return true;
            }
        }

        return false;
    }
}
