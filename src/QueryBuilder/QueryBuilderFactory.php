<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Factory for creating QueryBuilder instances with proper dependency injection.
 */
class QueryBuilderFactory
{
    private readonly PropertyResolver $propertyResolver;

    private readonly AssociationResolver $associationResolver;

    private readonly FilterFactory $filterFactory;

    public function __construct(
        private readonly EntityDefinitionResolver $definitionResolver,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
        $this->propertyResolver = new PropertyResolver($definitionResolver);
        $this->associationResolver = new AssociationResolver($definitionResolver);
        $this->filterFactory = new FilterFactory();
    }

    /**
     * Create a new QueryBuilder for the specified entity.
     *
     * @param string $entityClass Entity class (e.g., ProductEntity::class)
     * @param string|null $alias Optional alias for the entity (e.g., 'p')
     */
    public function create(string $entityClass, ?string $alias = null): QueryBuilder
    {
        $queryBuilder = new QueryBuilder(
            $entityClass,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            $this->filterFactory
        );

        if ($alias !== null) {
            $queryBuilder->setAlias($alias);
        }

        // Set repository and context for execution
        $entityName = $this->definitionResolver->getEntityName($entityClass);
        $repository = $this->getRepository($entityName);

        $queryBuilder->setRepository($repository);

        return $queryBuilder;
    }

    /**
     * Get repository for entity name.
     *
     * @phpstan-return EntityRepository<EntityCollection<Entity>>
     */
    private function getRepository(string $entityName): EntityRepository
    {
        /** @phpstan-var EntityRepository<EntityCollection<Entity>> */
        return $this->definitionRegistry->getRepository($entityName);
    }
}
