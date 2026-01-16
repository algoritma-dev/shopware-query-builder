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

- **[HELPER_FUNCTION.md](HELPER_FUNCTION.md)** - Guide to the function `sw_query()`
  - All available methods
  - Usage examples
  - Automatic repository

- **[WITH_ALIAS_SUPPORT.md](WITH_ALIAS_SUPPORT.md)** - Aliases in associations
  - Linear queries vs callbacks
  - Complex examples
  - Best Practices

- **[CRITICITA_E_SOLUZIONI.md](CRITICITA_E_SOLUZIONI.md)** - Technical analysis
  - Resolved issues
  - Implemented solutions
  - EntityDefinitionResolver

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
    ->where('p.price', '>=', $minPrice)
    ->where('p.price', '<=', $maxPrice)
    ->where('p.stock', '>', 0)
    ->where('m.active', true)
    ->where('m.country', 'in', ['DE', 'AT', 'CH'])
    ->where('c.id', 'in', $categoryIds)
    ->where('t.taxRate', '<=', 19)
    ->orderBy('p.createdAt', 'DESC')
    ->paginate($page, 24)
    ->getPaginated();
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

**Version**: 2.1
**Target**: Shopware 6.7.x
**PHP**: 8.2+
**Last revision**: 2026-01-16
