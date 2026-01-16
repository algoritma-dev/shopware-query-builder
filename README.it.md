# Shopware Query Builder - Fluent API for Shopware 6.

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

### 🎯 Zero Configurazione
- ✅ Nessun mapping manuale richiesto
- ✅ Usa direttamente le `EntityDefinition` di Shopware
- ✅ Sempre sincronizzato con le Definition
- ✅ Validazione automatica di proprietà e associazioni

### 🌟 Alias per Query Lineari
```php
// ✅ Con alias - Lineare e chiaro!
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active', true)
    ->where('m.active', true)
    ->orderBy('m.name', 'ASC')

// ❌ Senza alias - Nesting complesso
sw_query(ProductEntity::class)
    ->where('active', true)
    ->with('manufacturer', fn($q) =>
        $q->where('active', true)
    )
```

### 🔥 Esecuzione Integrata
```php
->get()              // EntitySearchResult completo
->getEntities()      // Solo EntityCollection
->toArray()          // Array di entità
->getIds()           // IdSearchResult
->getIdsArray()      // Array di IDs
->getOneOrNull()     // Prima entità o null
->getOneOrThrow()    // Prima entità o exception
->first()            // Alias di getOneOrNull
->firstOrFail()      // Alias di getOneOrThrow
->count()            // Conta risultati
->exists()           // Verifica esistenza
->doesntExist()      // Verifica non esistenza
->getPaginated()     // Array paginazione formattato
```

### 🛡️ Type Safety e Validazione
```php
// Validazione automatica con messaggi utili
try {
    sw_query(ProductEntity::class)
        ->where('invalidProperty', true);
} catch (InvalidPropertyException $e) {
    // "Property 'invalidProperty' does not exist on ProductEntity.
    //  Available properties: id, name, productNumber, stock, ..."
}
```

## 📦 Installazione

```bash
composer require yourvendor/shopware-query-builder
```

## 📚 Documentazione

### Documenti Principali

- **[AGENTS.md](AGENTS.md)** - Documentazione completa del progetto
  - Architettura
  - Componenti
  - Implementazione
  - Best practices
  - API Reference completa

- **[HELPER_FUNCTION.md](HELPER_FUNCTION.md)** - Guida alla funzione `sw_query()`
  - Tutti i metodi disponibili
  - Esempi d'uso
  - Repository automatico

- **[WITH_ALIAS_SUPPORT.md](WITH_ALIAS_SUPPORT.md)** - Alias nelle associazioni
  - Query lineari vs callback
  - Esempi complessi
  - Best practices

- **[CRITICITA_E_SOLUZIONI.md](CRITICITA_E_SOLUZIONI.md)** - Analisi tecnica
  - Criticità risolte
  - Soluzioni implementate
  - EntityDefinitionResolver

## 🎨 Esempi

### Esempio 1: Lista Prodotti

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

### Esempio 2: Dettaglio Prodotto

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

### Esempio 3: Search

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

### Esempio 4: Filtri Complessi

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

## 🔧 Configurazione

### 1. Registra servizi in `services.xml`

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

### 2. Registra helper in `composer.json`

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

### 1. Usa alias per query chiare
```php
// ✅ Chiaro quale campo appartiene a quale entity
sw_query(ProductEntity::class, 'p')
    ->with('manufacturer', 'm')
    ->where('p.active', true)
    ->where('m.active', true)
```

### 2. Registra associazioni prima di usare l'alias
```php
// ✅ Corretto
->with('manufacturer', 'm')  // Prima registra
->where('m.active', true)    // Poi usa

// ❌ Errore
->where('m.active', true)    // Alias non registrato!
->with('manufacturer', 'm')
```

### 3. Usa callback per OR complessa
```php
// ✅ Usa alias per filtri semplici
->with('manufacturer', 'm')
->where('m.active', true)

// ✅ Usa callback per logica OR
->with('categories', function($q) {
    $q->where('visible', true)
      ->orWhere('featured', true);
})
```

### 4. Gestisci eccezioni per getOneOrThrow
```php
try {
    $product = sw_query(ProductEntity::class)
        ->where('id', $id)
        ->getOneOrThrow();
} catch (EntityNotFoundException $e) {
    // Gestisci not found
}
```

### 5. Usa exists() per verifiche
```php
// ✅ Più efficiente
if (sw_query(ProductEntity::class)->where('id', $id)->exists()) {
    // ...
}

// ❌ Meno efficiente
if (sw_query(ProductEntity::class)->where('id', $id)->count() > 0) {
    // ...
}
```

## 🎯 Vantaggi

### vs Criteria Nativa
```php
// ❌ Shopware Criteria (verboso)
$criteria = new Criteria();
$criteria->addFilter(new EqualsFilter('active', true));
$criteria->addFilter(new RangeFilter('stock', [RangeFilter::GT => 0]));
$criteria->addAssociation('manufacturer');
$criteria->getAssociation('manufacturer')
    ->addFilter(new EqualsFilter('active', true));
$result = $repository->search($criteria, $context);

// ✅ Query Builder (intuitivo)
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

- ✅ **Zero overhead**: Compilato a `Criteria` identico
- ✅ **Validazione runtime**: Errori prima dell'esecuzione
- ✅ **Cache**: EntityDefinition cachate in memoria
- ✅ **Lazy loading**: Repository risolti on-demand

## 🤝 Contribuire

Contributi benvenuti! Vedi [AGENTS.md](AGENTS.md) per dettagli architetturali.

## 📝 Licenza

MIT

## 🙏 Credits

Sviluppato per Shopware 6.7+ con focus su:
- Developer Experience
- Type Safety
- Zero Configuration
- Modern PHP 8.2+

---

**Versione**: 2.1
**Target**: Shopware 6.7.x
**PHP**: 8.2+
**Ultima revisione**: 2026-01-16
