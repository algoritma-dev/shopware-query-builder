# Shopware Query Builder - Complete Technical Documentation

## Table of Contents

1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Architecture](#architecture)
4. [Key Features](#key-features)
5. [Implementation Details](#implementation-details)
6. [API Reference](#api-reference)
7. [Usage Examples](#usage-examples)
8. [Best Practices](#best-practices)

---

## Project Overview

### What is Shopware Query Builder?

Shopware Query Builder is a modern, fluent API library for building database queries in Shopware 6.7+. It provides an intuitive, Doctrine-like syntax that dramatically improves developer experience compared to Shopware's native Criteria API.

### The Problem

Shopware's native query building using `Criteria` is verbose and unintuitive:

```php
// ❌ Native Shopware Criteria (verbose and complex)
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('active', true));
$criteria->addFilter(new RangeFilter('stock', [RangeFilter::GT => 0]));
$criteria->addAssociation('manufacturer');
$criteria->getAssociation('manufacturer')
    ->addFilter(new EqualsFilter('active', true));
$result = $repository->search($criteria, $context);
```

### The Solution

Our Query Builder provides a clean, fluent interface:

```php
// ✅ Query Builder (clean and intuitive)
$result = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = true')
    ->where('p.stock > 0')
    ->where('m.active = true')
    ->get();
```

### Key Innovations

1. **Zero Configuration**: Automatically reads Shopware's EntityDefinition classes
2. **Alias Support**: Linear queries instead of nested callbacks
3. **Type Safety**: Runtime validation with helpful error messages
4. **Integrated Execution**: Built-in methods like `get()`, `exists()`, `count()`
5. **Modern PHP**: Uses PHP 8.2+ features (readonly properties, constructor promotion, etc.)

---

## Technology Stack

### Core Technologies

| Technology | Version | Purpose |
|------------|---------|---------|
| **PHP** | 8.2+ | Programming language |
| **Shopware** | 6.7.x | E-commerce platform |
| **Symfony** | 6.x/7.x | DI Container, Services |
| **Doctrine DBAL** | 3.x | (via Shopware) Database abstraction |

### Development Tools

| Tool | Purpose |
|------|---------|
| **PHPUnit** | Unit and integration testing |
| **PHPStan** | Static analysis (Level 9) |
| **PHP-CS-Fixer** | Code style enforcement |
| **Rector** | Automated refactoring |

### Architecture Patterns

- **Builder Pattern**: Fluent query construction
- **Factory Pattern**: QueryBuilderFactory for dependency injection
- **Repository Pattern**: Repository resolution and execution
- **Strategy Pattern**: Filter creation based on operators
- **Chain of Responsibility**: Query building pipeline

---

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        User Code                             │
│              sw_query(ProductEntity::class)                  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                  QueryBuilderFactory                         │
│         (Dependency Injection & Instantiation)               │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                     QueryBuilder                             │
│   (Fluent API: where(), with(), orderBy(), etc.)            │
└──────────┬──────────────────────────────────────┬───────────┘
           │                                       │
           ▼                                       ▼
┌──────────────────────┐              ┌──────────────────────┐
│ EntityDefinitionReso│              │   CriteriaBuilder    │
│  lver                │              │   (Criteria          │
│ • Property Validation│              │    Compilation)      │
│ • Association Info   │              └──────────┬───────────┘
└──────────┬───────────┘                         │
           │                                     │
           ▼                                     ▼
┌──────────────────────┐              ┌──────────────────────┐
│  PropertyResolver    │              │   FilterFactory      │
│  • Validate fields   │              │   • Create filters   │
│  • Resolve nested    │              │   • Map operators    │
└──────────────────────┘              └──────────────────────┘
                                                 │
                                                 ▼
┌─────────────────────────────────────────────────────────────┐
│             Shopware Repository & Context                    │
│                 (Query Execution)                            │
└─────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                  EntitySearchResult                          │
│              (Results with Entities)                         │
└─────────────────────────────────────────────────────────────┘
```

### Directory Structure

```
src/
├── DependencyInjection/          # Symfony DI Extension
│   └── ShopwareQueryBuilderExtension.php
├── Exception/                     # Custom exceptions
│   ├── EntityNotFoundException.php
│   ├── InvalidAliasException.php
│   ├── InvalidEntityException.php
│   ├── InvalidOperatorException.php
│   └── InvalidPropertyException.php
├── Filter/                        # Filter creation
│   ├── Expressions/
│   │   └── WhereExpression.php
│   ├── FilterFactory.php          # Creates Shopware filters
│   └── OperatorMapper.php         # Maps operators to filter types
├── Mapping/                       # Entity metadata resolution
│   ├── AssociationResolver.php    # Resolves associations
│   ├── EntityDefinitionResolver.php # Core resolver
│   └── PropertyResolver.php       # Validates properties
├── QueryBuilder/                  # Core query building
│   ├── CriteriaBuilder.php        # Compiles to Criteria
│   ├── QueryBuilder.php           # Main fluent API
│   └── QueryBuilderFactory.php    # Factory with DI
├── Repository/                    # Repository resolution
│   └── RepositoryResolver.php     # Resolves entity repositories
├── Resources/                     # Bundle resources
│   └── config/
│       └── services.xml           # Service definitions
├── helpers.php                    # Global helper function
└── ShopwareQueryBuilderBundle.php # Symfony bundle class
```

---

## Key Features

### 1. Zero Configuration

**How it works**: The library automatically reads Shopware's EntityDefinition classes via `DefinitionInstanceRegistry`.

**Benefits**:
- ✅ No manual mapping files
- ✅ Always synchronized with Shopware's schema
- ✅ Automatic validation
- ✅ Works with all Shopware entities out of the box

**Implementation**:
```php
class EntityDefinitionResolver
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {}

    public function getDefinition(string $entityClass): EntityDefinition
    {
        // Automatically resolve: ProductEntity -> ProductDefinition
        $definitionClass = str_replace('Entity', 'Definition', $entityClass);
        $entityName = constant($definitionClass . '::ENTITY_NAME');
        return $this->definitionRegistry->getByEntityName($entityName);
    }
}
```

### 2. Alias Support for Linear Queries

**The Innovation**: Most query builders force nested callbacks for associations. We introduced alias support for flat, SQL-like queries.

**Comparison**:

```php
// ❌ Traditional nested callbacks
sw_query(ProductEntity::class)
    ->where('active = true')
    ->with('manufacturer', fn($q) =>
        $q->where('active = true')
          ->where('country = "DE"')
    )
    ->with('categories', fn($q) =>
        $q->where('visible = true')
    )

// ✅ With aliases - linear and clear!
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->with('categories', 'c')
    ->where('p.active = true')
    ->where('m.active = true')
    ->where('m.country = "DE"')
    ->where('c.visible = true')
```

**Why it matters**:
- More readable and maintainable
- Easier to add/remove conditions
- Familiar to developers from SQL/Doctrine
- Better for complex queries with multiple joins

### 3. Type-Safe Property Validation

**Runtime validation** with helpful error messages:

```php
try {
    sw_query(ProductEntity::class)->where('invalidField = true');
} catch (InvalidPropertyException $e) {
    // Error: "Property 'invalidField' does not exist on ProductEntity.
    //         Available properties: id, name, productNumber, stock, active, ..."
}
```

### 4. Integrated Query Execution

**No need for separate repository calls**:

```php
// Get results
$products = sw_query(ProductEntity::class)->where('active = true')->get();

// Get one or throw
$product = sw_query(ProductEntity::class)->where('id = "' . $id . '"')->getOneOrThrow();

// Check existence
$exists = sw_query(ProductEntity::class)->where('id = "' . $id . '"')->exists();

// Count
$count = sw_query(ProductEntity::class)->where('active = true')->count();

// Pagination
$pagination = sw_query(ProductEntity::class)
    ->where('active = true')
    ->paginate(2, 20)
    ->getPaginated();
```

### 5. Comprehensive Operator Support

All common SQL operators mapped to Shopware filters:

| Operator | Shopware Filter | Example |
|----------|----------------|---------|
| `=`, `==` | EqualsFilter | `->where('active = true')` |
| `!=`, `<>` | NotFilter + EqualsFilter | `->where('status != "deleted"')` |
| `>`, `>=`, `<`, `<=` | RangeFilter | `->where('stock > 0')` |
| `LIKE` | ContainsFilter | `->where('name LIKE "%test%"')` |
| `IN` | EqualsAnyFilter | `->whereIn('id', $ids)` |
| `NOT IN` | NotFilter + EqualsAnyFilter | `->whereNotIn('id', $ids)` |

---

## Implementation Details

### Core Components

#### 1. QueryBuilder

The main fluent API class:

```php
class QueryBuilder
{
    private ?string $alias = null;
    private array $whereExpressions = [];
    private array $associations = [];
    private array $sortings = [];
    private ?int $limit = null;
    private ?int $offset = null;

    // Fluent methods
    public function where(string $expression): self
    public function whereRaw(string $expression): self
    public function with(string $association, $aliasOrCallback = null): self
    public function orderBy(string $property, string $direction = 'ASC'): self
    public function limit(int $limit): self
    public function offset(int $offset): self

    // Execution methods
    public function get(): EntitySearchResult
    public function getEntities(): EntityCollection
    public function getOneOrNull(): ?Entity
    public function getOneOrThrow(): Entity
    public function count(): int
    public function exists(): bool
    public function getPaginated(): array
}
```

#### 2. EntityDefinitionResolver

Resolves entity metadata from Shopware's registry:

```php
class EntityDefinitionResolver
{
    private array $cache = [];

    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {}

    public function getDefinition(string $entityClass): EntityDefinition
    public function hasField(string $entityClass, string $fieldName): bool
    public function isAssociation(string $entityClass, string $fieldName): bool
    public function getAssociationInfo(string $entityClass, string $fieldName): array
}
```

#### 3. CriteriaBuilder

Compiles QueryBuilder to Shopware Criteria:

```php
class CriteriaBuilder
{
    public function build(QueryBuilder $queryBuilder): Criteria
    {
        $criteria = new Criteria();

        // Add WHERE filters
        $this->addWhereFilters($criteria, $queryBuilder);

        // Add associations
        $this->addAssociations($criteria, $queryBuilder);

        // Add sorting
        $this->addSortings($criteria, $queryBuilder);

        // Set limit/offset
        $this->setLimitAndOffset($criteria, $queryBuilder);

        return $criteria;
    }
}
```

#### 4. FilterFactory

Creates appropriate Shopware filters:

```php
class FilterFactory
{
    public function create(string $field, string $operator, $value): Filter
    {
        return match ($this->operatorMapper->getFilterType($operator)) {
            'equals' => new EqualsFilter($field, $value),
            'range' => $this->createRangeFilter($field, $operator, $value),
            'contains' => new ContainsFilter($field, $value),
            'in' => new EqualsAnyFilter($field, $value),
            // ... more cases
        };
    }
}
```

#### 5. RawExpressionParser

Parses raw SQL-like expressions into structured data:

```php
class RawExpressionParser
{
    /**
     * Parse expression like 'stock > 10' or 'stock > 10 AND active = true'
     *
     * @return array{
     *     isCompound: bool,
     *     conditions: array<array{field: string, operator: string, value: mixed, raw: string}>,
     *     operator: 'AND'|'OR'|null
     * }
     */
    public function parse(string $expression): array

    public function isCompoundExpression(string $expression): bool
}
```

**Key Features:**
- Supports all operators: =, !=, <>, >, >=, <, <=, LIKE, IN, NOT IN, IS NULL, IS NOT NULL
- Handles value types: strings (quoted), numbers, booleans, null, arrays
- Detects logical operators (AND/OR) at top level
- Parses compound expressions into multiple conditions
- Returns raw expression for debugging

**Example:**
```php
$parser->parse('stock > 10');
// Returns:
[
    'isCompound' => false,
    'conditions' => [
        ['field' => 'stock', 'operator' => '>', 'value' => 10, 'raw' => 'stock > 10']
    ],
    'operator' => null
]

$parser->parse('stock > 10 AND active = true');
// Returns:
[
    'isCompound' => true,
    'conditions' => [
        ['field' => 'stock', 'operator' => '>', 'value' => 10, 'raw' => 'stock > 10'],
        ['field' => 'active', 'operator' => '=', 'value' => true, 'raw' => 'active = true']
    ],
    'operator' => 'AND'
]
```

### Alias Resolution System

**How aliases work**:

1. User registers alias: `->with('manufacturer', 'm')`
2. Alias is stored in `$aliasMap`: `['m' => 'manufacturer']`
3. When filtering: `->where('m.active', true)`
4. PropertyResolver checks if `'m'` is an alias
5. Resolves to `'manufacturer.active'`
6. Validates against EntityDefinition
7. Adds filter to correct association in Criteria

```php
// In PropertyResolver
public function resolve(string $entityClass, string $property): string
{
    // Check for alias prefix
    if (strpos($property, '.') !== false) {
        [$prefix, $rest] = explode('.', $property, 2);

        if (isset($this->aliasMap[$prefix])) {
            // Resolve alias: m.active -> manufacturer.active
            $property = $this->aliasMap[$prefix] . '.' . $rest;
        }
    }

    // Validate resolved property
    return $this->validateProperty($entityClass, $property);
}
```

---

## API Reference

### Helper Function

#### `sw_query(string $entityClass, ?string $alias = null): QueryBuilder`

Creates a new QueryBuilder instance.

**Parameters:**
- `$entityClass` - Entity class (e.g., `ProductEntity::class`)
- `$alias` - Optional alias for the main entity

**Example:**
```php
sw_query(ProductEntity::class, 'p')
```

### QueryBuilder Methods

#### Building Methods

##### `where(string $expression): self`

Add WHERE condition using raw SQL-like expression.

**Supports:**
- Simple expressions: `'stock > 10'`
- Compound AND: `'stock > 10 AND active = true'` (auto-creates GroupExpression)
- Compound OR: `'featured = true OR promoted = true'` (auto-creates GroupExpression)

**Examples:**
```php
->where('active = true')
->where('stock > 0')
->where('price >= 10')
->where('status = "active"')
->where('name LIKE "%test%"')
->where('stock > 10 AND active = true')  // Auto-grouped
->where('featured = true OR promoted = true')  // Auto-grouped
```

##### `whereRaw(string $expression): self`

Alias for `where()` - provides semantic clarity when using raw expressions.

##### `with(string $association, string|callable|null $aliasOrCallback = null): self`

Eager load association.

**Examples:**
```php
->with('manufacturer')                     // Simple
->with('manufacturer', 'm')                // With alias
->with('manufacturer', fn($q) =>           // With callback
    $q->where('active', true)
)
->with('cover.media')                      // Nested
```

##### `orderBy(string $property, string $direction = 'ASC'): self`

Add sorting.

**Examples:**
```php
->orderBy('name')                          // ASC default
->orderBy('createdAt', 'DESC')            // Explicit DESC
->orderBy('m.name', 'ASC')                // With alias
```

##### `limit(int $limit): self`

Set maximum results.

##### `offset(int $offset): self`

Set result offset (skip).

##### `paginate(int $page, int $perPage = 15): self`

Set pagination (auto-calculates limit/offset).

#### Execution Methods

##### `get(): EntitySearchResult`

Execute and get full search result.

##### `getEntities(): EntityCollection`

Execute and get only entity collection.

##### `toArray(): array`

Execute and get array of entities.

##### `getIds(): IdSearchResult`

Execute and get only IDs.

##### `getIdsArray(): array<string>`

Execute and get array of ID strings.

##### `getOneOrNull(): ?Entity`

Get first entity or null.

##### `getOneOrThrow(): Entity`

Get first entity or throw `EntityNotFoundException`.

##### `first(): ?Entity`

Alias of `getOneOrNull()`.

##### `firstOrFail(): Entity`

Alias of `getOneOrThrow()`.

##### `count(): int`

Count matching results.

##### `exists(): bool`

Check if any results exist.

##### `doesntExist(): bool`

Check if no results exist.

##### `getPaginated(): array`

Get formatted pagination data:
```php
[
    'data' => EntityCollection,
    'total' => int,
    'page' => int,
    'perPage' => int,
    'lastPage' => int,
    'hasMorePages' => bool
]
```

---

## Usage Examples

### Basic Query

```php
$products = sw_query(ProductEntity::class)
    ->where('active = true')
    ->where('stock > 0')
    ->orderBy('name', 'ASC')
    ->limit(20)
    ->getEntities();
```

### Query with Associations (Aliases)

```php
$products = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->with('categories', 'c')
    ->where('p.active = true')
    ->where('m.active = true')
    ->where('c.visible = true')
    ->orderBy('p.createdAt', 'DESC')
    ->get();
```

### Controller Example

```php
#[Route('/products')]
class ProductController extends AbstractController
{
    #[Route('/list', name: 'product.list')]
    public function list(Request $request): Response
    {
        $pagination = sw_query(ProductEntity::class, 'p')
            ->with('manufacturer', 'm')
            ->with('cover.media')
            ->where('p.active = true')
            ->where('p.stock > 0')
            ->where('m.active = true')
            ->orderBy('p.name', 'ASC')
            ->paginate($request->query->getInt('page', 1), 24)
            ->getPaginated();

        return $this->render('product/list.html.twig', $pagination);
    }

    #[Route('/detail/{id}', name: 'product.detail')]
    public function detail(string $id): Response
    {
        try {
            $product = sw_query(ProductEntity::class)
                ->where('id = "' . $id . '"')
                ->where('active = true')
                ->with('manufacturer')
                ->with('categories')
                ->with('media.media')
                ->getOneOrThrow();
        } catch (EntityNotFoundException $e) {
            throw $this->createNotFoundException('Product not found');
        }

        return $this->render('product/detail.html.twig', [
            'product' => $product
        ]);
    }
}
```

### Search with OR Conditions

```php
$term = $request->query->get('q');

$products = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = true')
    ->where('p.name LIKE "' . $term . '"')
    ->orWhere(function($q) use ($term) {
        $q->where('description LIKE "' . $term . '"')
          ->where('productNumber LIKE "' . $term . '"');
    })
    ->orderBy('p.name', 'ASC')
    ->limit(50)
    ->getEntities();
```

---

## Best Practices

### 1. Always Use Aliases for Complex Queries

```php
// ✅ Good - Clear and maintainable
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->with('categories', 'c')
    ->where('p.active = true')
    ->where('m.active = true')
    ->where('c.visible = true')

// ❌ Avoid - Nested callbacks are harder to read
sw_query(ProductEntity::class)
    ->where('active = true')
    ->with('manufacturer', fn($q) => $q->where('active = true'))
    ->with('categories', fn($q) => $q->where('visible = true'))
```

### 2. Register Associations Before Using Aliases

```php
// ✅ Correct order
->with('manufacturer', 'm')    // Register first
->where('m.active = true')     // Use after

// ❌ Wrong - Will throw InvalidAliasException
->where('m.active = true')     // Error: alias not registered!
->with('manufacturer', 'm')
```

### 3. Use Appropriate Execution Methods

```php
// ✅ Use exists() for existence checks
if (sw_query(ProductEntity::class)->where('id = "' . $id . '"')->exists()) {
    // ...
}

// ❌ Avoid - Less efficient
if (sw_query(ProductEntity::class)->where('id = "' . $id . '"')->count() > 0) {
    // ...
}
```

### 4. Handle Exceptions

```php
try {
    $product = sw_query(ProductEntity::class)
        ->where('id = "' . $id . '"')
        ->getOneOrThrow();
} catch (EntityNotFoundException $e) {
    // Handle not found
    throw $this->createNotFoundException();
}
```

### 5. Limit Results When Possible

```php
// ✅ Always set a limit for lists
$products = sw_query(ProductEntity::class)
    ->where('active = true')
    ->limit(100)
    ->getEntities();

// ❌ Avoid - Could load thousands of records
$products = sw_query(ProductEntity::class)
    ->where('active = true')
    ->getEntities();
```

---

## Performance Considerations

### Zero Overhead

The QueryBuilder compiles to identical `Criteria` objects as manual construction:

```php
// These produce IDENTICAL Criteria:

// Manual
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('active', true));
$result = $repo->search($criteria, $context);

// Query Builder
$result = sw_query(ProductEntity::class)
    ->where('active = true')
    ->get();
```

### Caching

- EntityDefinitions are cached in memory after first access
- No database queries for validation
- Minimal object creation overhead

### Query Optimization

- Use `getIds()` when you only need IDs
- Eager load associations with `with()` to avoid N+1
- Use `limit()` to restrict result sets
- Use `exists()` instead of `count() > 0`

---

## Testing

### Unit Tests

Located in `tests/Unit/`, covering:
- QueryBuilder methods
- Filter creation
- Property resolution
- Alias handling

### Integration Tests

Located in `tests/Integration/`, testing:
- Criteria compilation
- Repository integration
- Actual query execution

### Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests
composer test:integration

# With coverage
composer test:coverage
```

---

## Contributing

### Development Setup

```bash
# Clone repository
git clone https://github.com/algoritma/shopware-query-builder.git
cd shopware-query-builder

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer cs:check

# Fix code style
composer cs:fix

# Run static analysis
composer phpstan
```

### Code Standards

- PHP 8.2+ with strict types
- PSR-12 code style
- PHPStan level 9
- 100% type coverage
- Comprehensive PHPDoc

---

## Version History

### v3.0.0 (Current) - BREAKING CHANGES
- 🔥 **BREAKING**: Complete refactoring of WHERE clause syntax
- ✨ Raw SQL-like expressions: `where('field operator value')`
- ✨ Auto-grouping for compound AND/OR expressions
- ✨ New `RawExpressionParser` for parsing expressions
- 📚 Comprehensive migration guide
- ♻️ All tests rewritten for new syntax
- 🎯 Improved developer experience and code readability

### v2.1
- ✨ Added alias support for associations
- ✨ Integrated execution methods (`get()`, `exists()`, etc.)
- ✨ Global `sw_query()` helper function
- ✨ Pagination support with `getPaginated()`
- 🐛 Fixed nested property resolution
- 📚 Comprehensive documentation

### v2.0
- ✨ Zero-configuration approach using EntityDefinitionResolver
- ✨ Automatic property validation
- ✨ Runtime error messages with suggestions
- 🔨 Refactored to use Shopware's DefinitionInstanceRegistry

### v1.0
- 🎉 Initial release
- ✨ Basic fluent query API
- ✨ WHERE, WITH, ORDER BY support
- ✨ Manual entity mapping

---

## License

MIT License - see LICENSE file for details.

## Credits

Developed by [Algoritma](https://github.com/algoritma) for Shopware 6.7+

**Focus Areas:**
- Developer Experience
- Type Safety
- Zero Configuration
- Modern PHP Practices

---

**Last Updated:** 2026-02-11
**Version:** 1.0.0-dev
**Target:** Shopware 6.7.x
**PHP:** 8.2+
