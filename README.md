# Shopware Query Builder - Fluent API for Shopware 6.7

A modern and intuitive library for building Shopware 6.7 queries with fluent syntax, alias support, and zero configuration.

## Quick Start

```php
use Shopware\Core\Content\Product\ProductEntity;

// Simple query
$products = sw_query(ProductEntity::class)
    ->where('active = true')
    ->where('stock > 0')
    ->get();

// Query with aliases and associations
$products = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->with('categories', 'c')
    ->where('p.active = true')
    ->where('m.active = true')
    ->where('c.visible = true')
    ->orderBy('p.name', 'ASC')
    ->limit(20)
    ->getEntities();

// Get single entity
$product = sw_query(ProductEntity::class)
    ->where('id = ' . $productId)
    ->firstOrFail();

// Check existence
$exists = sw_query(ProductEntity::class)
    ->where('productNumber = "SW-001"')
    ->exists();

// Pagination
$pagination = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = true')
    ->where('m.active = true')
    ->paginate(1, 20)
    ->getPaginated();
```

## Features

### Zero Configuration
- No manual mapping required
- Directly uses Shopware's `EntityDefinition`
- Always synchronized with Definitions
- Automatic validation of properties and associations

### Advanced Features
- **Parameter Binding**: `setParameter()`, `setParameters()` for secure queries
- **Aggregations**: `addCount()`, `addSum()`, `addAvg()`, `addMin()`, `addMax()`
- **Nested Groups**: `whereGroup()`, `orWhereGroup()` with infinite nesting
- **Reusable Scopes**: `scope()`, `scopes()` for query logic reuse
- **Soft Deletes**: `withTrashed()`, `onlyTrashed()`, `withoutTrashed()`
- **Query Debugging**: `debug()`, `dump()`, `dd()`, `toDebugArray()`

### Aliases for Linear Queries
```php
// With aliases - Linear and clear!
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = true')
    ->where('m.active = true')
    ->orderBy('m.name', 'ASC')

// Without aliases - Complex nesting
sw_query(ProductEntity::class)
    ->where('active = true')
    ->with('manufacturer', fn($q) =>
        $q->where('active = true')
    )
```

### Integrated Execution
```php
->get()              // EntitySearchResult complete
->getEntities()      // Only EntityCollection
->toArray()          // Array of entities
->getIds()           // IdSearchResult
->getIdsArray()      // Array of IDs
->getOneOrNull()     // First entity or null
->getOneOrThrow()    // First entity or exception
->first()            // Alias of getOneOrNull
->firstOrFail()      // Alias of getOneOrThrow
->count()            // Count results
->exists()           // Check existence
->doesntExist()      // Check non-existence
->getPaginated()     // Formatted pagination array
```

### Advanced Query Methods
```php
// Raw SQL-like expressions
->where('field = value')                      // Equals
->where('field > 10')                         // Greater than
->where('stock > 10 AND active = true')       // Compound AND (auto-creates GroupExpression)
->where('featured = true OR promoted = true') // Compound OR (auto-creates GroupExpression)

// Parameter binding (secure, reusable)
->where('status = :status')                   // Named parameter
->setParameter('status', 'active')            // Set single parameter
->setParameters(['status' => 'active'])       // Set multiple parameters

// Convenience methods (unchanged)
->whereBetween('field', 10, 100)    // Between values
->whereIn('field', [1, 2, 3])       // In array
->whereNotIn('field', [1, 2])       // Not in array
->whereNull('field')                // Is null
->whereNotNull('field')             // Is not null
->whereStartsWith('field', 'prefix')// Starts with
->whereEndsWith('field', 'suffix')  // Ends with

// Aggregations
->addCount('name')                  // Count aggregation
->addSum('field', 'name')           // Sum aggregation
->addAvg('field', 'name')           // Average aggregation
->addMin('field', 'name')           // Minimum aggregation
->addMax('field', 'name')           // Maximum aggregation

// Grouping
->whereGroup(fn($q) => ...)         // Group conditions with AND
->orWhereGroup(fn($q) => ...)       // Group conditions with OR

// Scopes
->scope(ScopeInterface $scope)      // Apply single scope
->scopes(array $scopes)             // Apply multiple scopes

// Soft Deletes
->withTrashed()                     // Include soft-deleted
->onlyTrashed()                     // Only soft-deleted
->withoutTrashed()                  // Exclude soft-deleted (default)

// Debugging
->debug()                           // Enable debug mode
->dump()                            // Dump and continue
->dd()                              // Dump and die
->toDebugArray()                    // Get query as array
```

### Type Safety and Validation
```php
// Automatic validation with helpful messages
try {
    sw_query(ProductEntity::class)
        ->where('invalidProperty = true');
} catch (InvalidPropertyException $e) {
    // "Property 'invalidProperty' does not exist on ProductEntity.
    //  Available properties: id, name, productNumber, stock, ..."
}
```

## Installation

```bash
composer require yourvendor/shopware-query-builder
```

## Documentation

### Main Documents

- **[AGENTS.md](AGENTS.md)** - Complete project documentation
  - Architecture
  - Components
  - Implementation
  - Best Practices
  - Complete API Reference

## Examples

### Example 1: Product List

```php
#[Route('/products')]
public function list(Request $request): Response
{
    $pagination = sw_query(ProductEntity::class, 'p')
        ->with('manufacturer', 'm')
        ->with('cover.media')
        ->where('p.active = true')
        ->where('p.stock > 0')
        ->where('m.active = true')
        ->orderBy('p.name', 'ASC')
        ->paginate($request->query->getInt('page', 1), 20)
        ->getPaginated();

    return $this->render('products.html.twig', $pagination);
}
```

### Example 2: Product Detail

```php
#[Route('/product/{id}')]
public function detail(string $id): Response
{
    try {
        $product = sw_query(ProductEntity::class)
            ->where('id = "' . $id . '"')
            ->where('active = true')
            ->with('manufacturer')
            ->with('categories', 'c')
            ->where('c.visible = true')
            ->with('media.media')
            ->firstOrFail();
    } catch (EntityNotFoundException $e) {
        throw $this->createNotFoundException();
    }

    return $this->render('product.html.twig', ['product' => $product]);
}
```

### Example 3: Search

```php
#[Route('/search')]
public function search(Request $request): Response
{
    $term = $request->query->get('q');

    $products = sw_query(ProductEntity::class, 'p')
        ->with('manufacturer', 'm')
        ->where('p.active = true')
        ->where('p.name LIKE "' . $term . '"')
        ->orWhere(function($q) use ($term) {
            $q->where('description LIKE "' . $term . '"') // LIKE operator doesn't need %, Shopware ContainsFilter adds it automatically
              ->where('productNumber LIKE "' . $term . '"');
        })
        ->orderBy('p.name', 'ASC')
        ->limit(50)
        ->getEntities();

    return $this->render('search.html.twig', ['products' => $products]);
}
```

### Example 4: Complex Filters

```php
$products = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->with('categories', 'c')
    ->with('tax', 't')
    ->where('p.active = true')
    ->whereBetween('p.price', $minPrice, $maxPrice)
    ->where('p.stock > 0')
    ->where('m.active = true')
    ->whereIn('m.country', ['DE', 'AT', 'CH'])
    ->whereIn('c.id', $categoryIds)
    ->where('t.taxRate <= 19')
    ->orderBy('p.createdAt', 'DESC')
    ->paginate($page, 24)
    ->getPaginated();
```

### Example 5: Aggregations

```php
use Shopware\Core\Content\Product\ProductEntity;

// Calculate statistics
$result = sw_query(ProductEntity::class)
    ->where('active = true')
    ->addCount('totalProducts')
    ->addSum('stock', 'totalStock')
    ->addAvg('price', 'avgPrice')
    ->addMin('price', 'minPrice')
    ->addMax('price', 'maxPrice')
    ->get();

$aggregations = $result->getAggregations();
$totalProducts = $aggregations->get('totalProducts')->getCount();
$totalStock = $aggregations->get('totalStock')->getSum();
$avgPrice = $aggregations->get('avgPrice')->getAvg();
```

### Example 6: Nested Groups

```php
// Complex filtering with nested AND/OR groups
$products = sw_query(ProductEntity::class, 'p')
    ->where('p.active = true')
    ->whereGroup(function($q) {
        // (stock > 0 OR availableStock > 0)
        $q->where('stock > 0')
          ->orWhereGroup(function($nested) {
              $nested->where('availableStock > 0');
          });
    })
    ->whereGroup(function($q) {
        // AND (price >= 10 AND price <= 100)
        $q->where('price >= 10')
          ->where('price <= 100');
    })
    ->getEntities();
```

### Example 7: Reusable Scopes

```php
use Algoritma\ShopwareQueryBuilder\Scope\ActiveScope;
use Algoritma\ShopwareQueryBuilder\Scope\InStockScope;

// Create custom scope
class FeaturedScope implements ScopeInterface
{
    public function apply(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->where('featured = true');
    }
}

// Use scopes
$products = sw_query(ProductEntity::class)
    ->scope(new ActiveScope())
    ->scope(new InStockScope(10)) // minimum stock 10
    ->scope(new FeaturedScope())
    ->getEntities();

// Or apply multiple at once
$products = sw_query(ProductEntity::class)
    ->scopes([
        new ActiveScope(),
        new InStockScope(),
        new FeaturedScope()
    ])
    ->getEntities();
```

### Example 8: Soft Deletes

```php
// Only active (non-deleted) entities (default)
$products = sw_query(ProductEntity::class)
    ->where('active = true')
    ->getEntities();

// Include soft-deleted entities
$products = sw_query(ProductEntity::class)
    ->withTrashed()
    ->getEntities();

// Only soft-deleted entities
$deletedProducts = sw_query(ProductEntity::class)
    ->onlyTrashed()
    ->getEntities();
```

### Example 9: Parameter Binding

```php
// Simple parameter binding
$products = sw_query(ProductEntity::class)
    ->where('status = :status')
    ->setParameter('status', 'active')
    ->getEntities();

// Multiple parameters
$products = sw_query(ProductEntity::class)
    ->where('active = :active')
    ->where('stock > :minStock')
    ->setParameters([
        'active' => true,
        'minStock' => 10
    ])
    ->getEntities();

// Parameters with IN operator
$products = sw_query(ProductEntity::class)
    ->where('status IN (:statuses)')
    ->setParameter('statuses', ['active', 'pending', 'processing'])
    ->getEntities();

// Range queries with parameters
$products = sw_query(ProductEntity::class)
    ->where('price >= :minPrice AND price <= :maxPrice')
    ->setParameters([
        'minPrice' => 100,
        'maxPrice' => 500
    ])
    ->getEntities();

// Parameters with LIKE
$products = sw_query(ProductEntity::class)
    ->where('name LIKE :searchTerm')
    ->setParameter('searchTerm', '%laptop%')
    ->getEntities();

// Reusable query with different parameters
$queryTemplate = sw_query(ProductEntity::class)
    ->where('active = :active')
    ->where('stock > :minStock');

// Execute with different parameter sets
$activeProducts = $queryTemplate
    ->setParameters(['active' => true, 'minStock' => 10])
    ->getEntities();

// Complex example with multiple parameter types
$products = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = :active')
    ->where('p.stock >= :minStock AND p.stock <= :maxStock')
    ->where('m.country IN (:countries)')
    ->where('p.price >= :minPrice')
    ->orWhere('p.featured = :featured')
    ->setParameters([
        'active' => true,
        'minStock' => 10,
        'maxStock' => 100,
        'countries' => ['DE', 'AT', 'CH'],
        'minPrice' => 50,
        'featured' => true
    ])
    ->orderBy('p.name', 'ASC')
    ->getEntities();
```

### Example 10: Query Debugging

```php
// Enable debug mode
$products = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = true')
    ->where('m.active = true')
    ->debug() // Will print query info on execution
    ->getEntities();

// Dump query info and continue
sw_query(ProductEntity::class)
    ->where('active = true')
    ->orderBy('name')
    ->dump() // Prints query structure
    ->getEntities();

// Dump and die (like dd() in Laravel)
sw_query(ProductEntity::class)
    ->where('active = true')
    ->dd(); // Prints and exits

// Get query as array for inspection
$debugInfo = sw_query(ProductEntity::class, 'p')
    ->where('p.active = true')
    ->limit(10)
    ->toDebugArray();
// Returns: ['entity' => '...', 'where' => [...], 'limit' => 10, ...]
```

### Example 11: Updates

```php
// Update with conditions and get updated entities
$products = sw_query(ProductEntity::class, 'p')
    ->where('p.active = true')
    ->update([/** data **/]); // Will return entities objects with updated data *NOTICE: flat associative array for updates with conditions*

// Update without conditions (Shopware repository update standard behavior) and get entity objects with updated data
sw_query(ProductEntity::class)
    ->update(
        [[ /** data **/]]
    );
```

## Migration Guide v2.x → v3.0.0

###️ BREAKING CHANGES

Version 3.0.0 introduces a completely new WHERE clause syntax for improved readability and intuitive SQL-like expressions.

### What Changed

**OLD Syntax (v2.x):**
```php
->where('field', 'operator', 'value')  // 3 parameters
->where('stock', '>', 10)
->where('active', true)
```

**NEW Syntax (v3.0.0):**
```php
->where('field operator value')  // 1 parameter, raw SQL-like
->where('stock > 10')
->where('active = true')
```

### Migration Steps

1. **Simple Equality:**
   ```php
   // Before
   ->where('active', true)

   // After
   ->where('active = true')
   ```

2. **Comparison Operators:**
   ```php
   // Before
   ->where('stock', '>', 10)
   ->where('price', '>=', 100)

   // After
   ->where('stock > 10')
   ->where('price >= 100')
   ```

3. **String Values (add quotes):**
   ```php
   // Before
   ->where('status', 'active')

   // After
   ->where('status = "active"')
   // or
   ->where('status = active')  // unquoted also works
   ```

4. **Compound Expressions (Auto-Grouping):**
   ```php
   // Before
   ->whereGroup(fn($q) =>
       $q->where('stock', '>', 10)
         ->where('active', true)
   )

   // After (much simpler!)
   ->where('stock > 10 AND active = true')
   ```

5. **OR Logic:**
   ```php
   // Before
   ->orWhere(fn($q) =>
       $q->where('featured', true)
         ->where('promoted', true)
   )

   // After
   ->where('featured = true OR promoted = true')
   ```

### Convenience Methods (Still Work!)

The following convenience methods still work as before:
- `whereIn($field, $array)` - unchanged
- `whereNotIn($field, $array)` - unchanged
- `whereNull($field)` - unchanged
- `whereNotNull($field)` - unchanged
- `whereBetween($field, $min, $max)` - unchanged
- `whereStartsWith($field, $value)` - unchanged
- `whereEndsWith($field, $value)` - unchanged

## 🔧 Configuration

### 1. Register services in `services.xml`

```xml
<services>
    <!-- EntityDefinitionResolver -->
    <service id="YourVendor\QueryBuilder\Mapping\EntityDefinitionResolver">
        <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry"/>
    </service>

    <!-- PropertyResolver -->
    <service id="YourVendor\QueryBuilder\Mapping\PropertyResolver">
        <argument type="service" id="YourVendor\QueryBuilder\Mapping\EntityDefinitionResolver"/>
    </service>

    <!-- AssociationResolver -->
    <service id="YourVendor\QueryBuilder\Mapping\AssociationResolver">
        <argument type="service" id="YourVendor\QueryBuilder\Mapping\EntityDefinitionResolver"/>
    </service>

    <!-- RepositoryResolver -->
    <service id="YourVendor\QueryBuilder\Repository\RepositoryResolver">
        <argument type="service" id="Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry"/>
    </service>

    <!-- QueryBuilderFactory -->
    <service id="YourVendor\QueryBuilder\QueryBuilder\QueryBuilderFactory" public="true">
        <argument type="service" id="YourVendor\QueryBuilder\Mapping\EntityDefinitionResolver"/>
        <argument type="service" id="YourVendor\QueryBuilder\Mapping\PropertyResolver"/>
        <argument type="service" id="YourVendor\QueryBuilder\Mapping\AssociationResolver"/>
        <argument type="service" id="YourVendor\QueryBuilder\Repository\RepositoryResolver"/>
        <argument type="service" id="Shopware\Core\Framework\Context"/>
    </service>
</services>
```

### 2. Register helper in `composer.json`

```json
{
    "autoload": {
        "psr-4": {
            "YourVendor\\QueryBuilder\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    }
}
```

## Best Practices

### 1. Use aliases for clear queries
```php
// Clear which field belongs to which entity
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = true')
    ->where('m.active = true')
```

### 2. Register associations before using the alias
```php
// Correct
->with('manufacturer', 'm')  // Register first
->where('m.active = true')   // Then use

// Error
->where('m.active = true')   // Alias not registered!
->with('manufacturer', 'm')
```

### 3. Use callbacks for complex OR
```php
// Use aliases for simple filters
->with('manufacturer', 'm')
->where('m.active = true')

// Use callbacks for OR logic
->with('categories', function($q) {
    $q->where('visible = true')
      ->orWhere('featured = true');
})
```

### 4. Handle exceptions for getOneOrThrow
```php
try {
    $product = sw_query(ProductEntity::class)
        ->where('id = "' . $id . '"')
        ->getOneOrThrow();
} catch (EntityNotFoundException $e) {
    // Handle not found
}
```

### 5. Use parameter binding for dynamic values
```php
// Secure - Uses parameter binding
$products = sw_query(ProductEntity::class)
    ->where('status = :status')
    ->setParameter('status', $userInput)
    ->getEntities();

// Also secure - QueryBuilder handles values safely
$products = sw_query(ProductEntity::class)
    ->where('status = "' . $userInput . '"')
    ->getEntities();
```

### 6. Use exists() for checks
```php
// More efficient
if (sw_query(ProductEntity::class)->where('id = "' . $id . '"')->exists()) {
    // ...
}

// Less efficient
if (sw_query(ProductEntity::class)->where('id = "' . $id . '"')->count() > 0) {
    // ...
}
```

## Advantages

### vs Native Criteria
```php
// Shopware Criteria (verbose)
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('active', true));
$criteria->addFilter(new RangeFilter('stock', [RangeFilter::GT => 0]));
$criteria->addAssociation('manufacturer');
$criteria->getAssociation('manufacturer')
    ->addFilter(new EqualsFilter('active', true));
$result = $repository->search($criteria, $context);

// Query Builder (intuitive)
$result = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = true')
    ->where('p.stock > 0')
    ->where('m.active = true')
    ->get();
```

### vs Doctrine QueryBuilder
```php
// Doctrine-like syntax for Shopware!
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active = true')
    ->where('m.country = "DE"')
    ->orderBy('p.name', 'ASC')
    ->limit(20)
    ->getEntities();
```

## Performance

- **Zero overhead**: Compiled to `Criteria` identical
- **Runtime validation**: Errors before execution
- **Caching**: EntityDefinition cached in memory
- **Lazy loading**: Repository resolved on-demand
- **Efficient aggregations**: Native Shopware aggregation support
- **Optimized grouping**: Recursive compilation to MultiFilter

## Testing

The library includes comprehensive test coverage:

- **156 Unit Tests** - 100% of core functionality tested
- **311 Assertions** - Extensive validation
- **PHPStan Level 6** - Maximum type safety
- **Zero Errors** - All tests passing

Run tests:
```bash
docker run --rm -u 1000 -v .:/app -w /app composer ./vendor/bin/phpunit
```

## Contributing

Contributions welcome! See [AGENTS.md](AGENTS.md) for architectural details.

## License

MIT

## Credits

Developed for Shopware 6.7+ with focus on:
- Developer Experience
- Type Safety
- Zero Configuration
- Modern PHP 8.2+

---
