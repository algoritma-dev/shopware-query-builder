# Shopware Query Builder - Fluent API for Shopware 6.7

A modern and intuitive library for building Shopware 6.7 queries with fluent syntax, alias support, and zero configuration.

## 🚀 Quick Start

```php
use Shopware\Core\Content\Product\ProductEntity;

// Simple query
$products = sw_query(ProductEntity::class)
    ->where('active', true)
    ->where('stock', '>', 0)
    ->get();

// Query with aliases and associations
$products = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->with('categories', 'c')
    ->where('p.active', true)
    ->where('m.active', true)
    ->where('c.visible', true)
    ->orderBy('p.name', 'ASC')
    ->limit(20)
    ->getEntities();

// Get single entity
$product = sw_query(ProductEntity::class)
    ->where('id', $productId)
    ->getOneOrThrow();

// Check existence
$exists = sw_query(ProductEntity::class)
    ->where('productNumber', 'SW-001')
    ->exists();

// Pagination
$pagination = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active', true)
    ->where('m.active', true)
    ->paginate(1, 20)
    ->getPaginated();
```

## ✨ Features

### 🎯 Zero Configuration
- ✅ No manual mapping required
- ✅ Directly uses Shopware's `EntityDefinition`
- ✅ Always synchronized with Definitions
- ✅ Automatic validation of properties and associations

### 🚀 Advanced Features
- ✅ **Aggregations**: `addCount()`, `addSum()`, `addAvg()`, `addMin()`, `addMax()`
- ✅ **Nested Groups**: `whereGroup()`, `orWhereGroup()` with infinite nesting
- ✅ **Reusable Scopes**: `scope()`, `scopes()` for query logic reuse
- ✅ **Soft Deletes**: `withTrashed()`, `onlyTrashed()`, `withoutTrashed()`
- ✅ **Query Debugging**: `debug()`, `dump()`, `dd()`, `toDebugArray()`

### 🌟 Aliases for Linear Queries
```php
// ✅ With aliases - Linear and clear!
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active', true)
    ->where('m.active', true)
    ->orderBy('m.name', 'ASC')

// ❌ Without aliases - Complex nesting
sw_query(ProductEntity::class)
    ->where('active', true)
    ->with('manufacturer', fn($q) =>
        $q->where('active', true)
    )
```

### 🔥 Integrated Execution
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

### 📊 Advanced Query Methods
```php
// Operators
->where('field', 'value')           // Equals
->where('field', '>', 10)           // Greater than
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

### 🛡️ Type Safety and Validation
```php
// Automatic validation with helpful messages
try {
    sw_query(ProductEntity::class)
        ->where('invalidProperty', true);
} catch (InvalidPropertyException $e) {
    // "Property 'invalidProperty' does not exist on ProductEntity.
    //  Available properties: id, name, productNumber, stock, ..."
}
```

## 📦 Installation

```bash
composer require yourvendor/shopware-query-builder
```

## 📚 Documentation

### Main Documents

- **[AGENTS.md](AGENTS.md)** - Complete project documentation
  - Architecture
  - Components
  - Implementation
  - Best Practices
  - Complete API Reference

## 🎨 Examples

### Example 1: Product List

```php
#[Route('/products')]
public function list(Request $request): Response
{
    $pagination = sw_query(ProductEntity::class, 'p')
        ->with('manufacturer', 'm')
        ->with('cover.media')
        ->where('p.active', true)
        ->where('p.stock', '>', 0)
        ->where('m.active', true)
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
            ->where('id', $id)
            ->where('active', true)
            ->with('manufacturer')
            ->with('categories', 'c')
            ->where('c.visible', true)
            ->with('media.media')
            ->getOneOrThrow();
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
        ->where('p.active', true)
        ->where('p.name', 'like', "%{$term}%")
        ->orWhere(function($q) use ($term) {
            $q->where('description', 'like', "%{$term}%")
              ->where('productNumber', 'like', "%{$term}%");
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
    ->where('p.active', true)
    ->whereBetween('p.price', $minPrice, $maxPrice)
    ->where('p.stock', '>', 0)
    ->where('m.active', true)
    ->whereIn('m.country', ['DE', 'AT', 'CH'])
    ->whereIn('c.id', $categoryIds)
    ->where('t.taxRate', '<=', 19)
    ->orderBy('p.createdAt', 'DESC')
    ->paginate($page, 24)
    ->getPaginated();
```

### Example 5: Aggregations

```php
use Shopware\Core\Content\Product\ProductEntity;

// Calculate statistics
$result = sw_query(ProductEntity::class)
    ->where('active', true)
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
    ->where('p.active', true)
    ->whereGroup(function($q) {
        // (stock > 0 OR availableStock > 0)
        $q->where('stock', '>', 0)
          ->orWhereGroup(function($nested) {
              $nested->where('availableStock', '>', 0);
          });
    })
    ->whereGroup(function($q) {
        // AND (price >= 10 AND price <= 100)
        $q->where('price', '>=', 10)
          ->where('price', '<=', 100);
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
        $queryBuilder->where('featured', true);
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
    ->where('active', true)
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

### Example 9: Query Debugging

```php
// Enable debug mode
$products = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active', true)
    ->where('m.active', true)
    ->debug() // Will print query info on execution
    ->getEntities();

// Dump query info and continue
sw_query(ProductEntity::class)
    ->where('active', true)
    ->orderBy('name')
    ->dump() // Prints query structure
    ->getEntities();

// Dump and die (like dd() in Laravel)
sw_query(ProductEntity::class)
    ->where('active', true)
    ->dd(); // Prints and exits

// Get query as array for inspection
$debugInfo = sw_query(ProductEntity::class, 'p')
    ->where('p.active', true)
    ->limit(10)
    ->toDebugArray();
// Returns: ['entity' => '...', 'where' => [...], 'limit' => 10, ...]
```

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

## 💡 Best Practices

### 1. Use aliases for clear queries
```php
// ✅ Clear which field belongs to which entity
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active', true)
    ->where('m.active', true)
```

### 2. Register associations before using the alias
```php
// ✅ Correct
->with('manufacturer', 'm')  // Register first
->where('m.active', true)    // Then use

// ❌ Error
->where('m.active', true)    // Alias not registered!
->with('manufacturer', 'm')
```

### 3. Use callbacks for complex OR
```php
// ✅ Use aliases for simple filters
->with('manufacturer', 'm')
->where('m.active', true)

// ✅ Use callbacks for OR logic
->with('categories', function($q) {
    $q->where('visible', true)
      ->orWhere('featured', true);
})
```

### 4. Handle exceptions for getOneOrThrow
```php
try {
    $product = sw_query(ProductEntity::class)
        ->where('id', $id)
        ->getOneOrThrow();
} catch (EntityNotFoundException $e) {
    // Handle not found
}
```

### 5. Use exists() for checks
```php
// ✅ More efficient
if (sw_query(ProductEntity::class)->where('id', $id)->exists()) {
    // ...
}

// ❌ Less efficient
if (sw_query(ProductEntity::class)->where('id', $id)->count() > 0) {
    // ...
}
```

## 🎯 Advantages

### vs Native Criteria
```php
// ❌ Shopware Criteria (verbose)
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('active', true));
$criteria->addFilter(new RangeFilter('stock', [RangeFilter::GT => 0]));
$criteria->addAssociation('manufacturer');
$criteria->getAssociation('manufacturer')
    ->addFilter(new EqualsFilter('active', true));
$result = $repository->search($criteria, $context);

// ✅ Query Builder (intuitive)
$result = sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active', true)
    ->where('p.stock', '>', 0)
    ->where('m.active', true)
    ->get();
```

### vs Doctrine QueryBuilder
```php
// Doctrine-like syntax ma per Shopware!
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active', true)
    ->where('m.country', 'DE')
    ->orderBy('p.name', 'ASC')
    ->limit(20)
    ->getEntities();
```

## 📊 Performance

- ✅ **Zero overhead**: Compiled to `Criteria` identical
- ✅ **Runtime validation**: Errors before execution
- ✅ **Caching**: EntityDefinition cached in memory
- ✅ **Lazy loading**: Repository resolved on-demand
- ✅ **Efficient aggregations**: Native Shopware aggregation support
- ✅ **Optimized grouping**: Recursive compilation to MultiFilter

## 🧪 Testing

The library includes comprehensive test coverage:

- **156 Unit Tests** - 100% of core functionality tested
- **311 Assertions** - Extensive validation
- **PHPStan Level 6** - Maximum type safety
- **Zero Errors** - All tests passing

Run tests:
```bash
docker run --rm -u 1000 -v .:/app -w /app composer ./vendor/bin/phpunit
```

## 🤝 Contributing

Contributions welcome! See [AGENTS.md](AGENTS.md) for architectural details.

## 📝 License

MIT

## 🙏 Credits

Developed for Shopware 6.7+ with focus on:
- Developer Experience
- Type Safety
- Zero Configuration
- Modern PHP 8.2+

---

**Version**: 3.0.0
**Target**: Shopware 6.7.x
**PHP**: 8.2+
**Last revision**: 2026-01-16

## 🆕 What's New in v3.0

### Major Features
- ✅ **Aggregations** - Full support for count, sum, avg, min, max
- ✅ **Nested Groups** - Infinite nesting with `whereGroup()` and `orWhereGroup()`
- ✅ **Reusable Scopes** - Extract and reuse query logic with `ScopeInterface`
- ✅ **Soft Deletes** - Built-in support with `withTrashed()`, `onlyTrashed()`
- ✅ **Query Debugging** - `debug()`, `dump()`, `dd()`, `toDebugArray()`

### Testing & Quality
- 156 unit tests (+41 from v2.1)
- 311 assertions (+90 from v2.1)
- 100% test success rate
- PHPStan Level 6 compliance
- Full type safety with generics
