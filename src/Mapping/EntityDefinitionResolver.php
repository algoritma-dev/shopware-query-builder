<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidEntityException;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;

/**
 * Resolves EntityDefinition metadata automatically from Shopware's DefinitionInstanceRegistry.
 *
 * Supports both traditional and attribute-based entities:
 * - Traditional: ProductEntity -> ProductDefinition (convention-based)
 * - Attribute-based: CustomProductEntity with #[Entity] attributes (mapping-based)
 *
 * This approach eliminates manual configuration by leveraging Shopware's native EntityDefinition classes.
 * All property and association validation happens automatically at runtime.
 */
class EntityDefinitionResolver
{
    /**
     * @var array<string, EntityDefinition>
     */
    private array $cache = [];

    /**
     * @var array<string, string> Custom mappings for attribute-based entities
     *                            Maps entity class to entity name (e.g., CustomProductEntity::class => 'custom_product')
     */
    private array $entityNameMapping = [];

    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {}

    /**
     * Register a custom entity name mapping for attribute-based entities.
     *
     * Use this to support entities created via PHP 8 attributes that don't follow
     * the traditional Entity -> Definition naming convention.
     *
     * Example:
     *   $resolver->registerEntityMapping(CustomProductEntity::class, 'custom_product');
     *
     * @param string $entityClass The fully qualified entity class name
     * @param string $entityName The entity name from Shopware's registry
     */
    public function registerEntityMapping(string $entityClass, string $entityName): void
    {
        $this->entityNameMapping[$entityClass] = $entityName;
        // Clear cache for this entity to ensure fresh resolution
        unset($this->cache[$entityClass]);
    }

    /**
     * Get the EntityDefinition for a given Entity class.
     *
     * Supports both traditional and attribute-based entities:
     * 1. Custom mappings (for attribute-based entities)
     * 2. Convention-based resolution (for traditional entities)
     * 3. Reflection-based discovery (for attribute-based entities with proper attributes)
     *
     * @throws InvalidEntityException
     */
    public function getDefinition(string $entityClass): EntityDefinition
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        // Custom mapping
        if (isset($this->entityNameMapping[$entityClass])) {
            $entityName = $this->entityNameMapping[$entityClass];

            $definition = $this->definitionRegistry->getByEntityName($entityName);

            $this->cache[$entityClass] = $definition;

            return $definition;
        }

        if (! class_exists($entityClass)) {
            throw new InvalidEntityException(\sprintf('Entity class %s does not exist. Please ensure it is autoloadable or register a custom mapping.', $entityClass));
        }

        $definition = $this->definitionRegistry->getByEntityClass(new $entityClass());

        if (! $definition instanceof EntityDefinition) {
            throw new InvalidEntityException(\sprintf('Could not resolve definition for entity %s', $entityClass));
        }

        $this->cache[$entityClass] = $definition;

        return $definition;
    }

    /**
     * Get the entity name (e.g., 'product').
     */
    public function getEntityName(string $entityClass): string
    {
        return $this->getDefinition($entityClass)->getEntityName();
    }

    /**
     * Check if a field exists in the Definition.
     */
    public function hasField(string $entityClass, string $fieldName): bool
    {
        $definition = $this->getDefinition($entityClass);
        $field = $definition->getField($fieldName);

        return $field instanceof Field;
    }

    /**
     * Get a Field from the Definition.
     *
     * @throws InvalidEntityException
     */
    public function getField(string $entityClass, string $fieldName): Field
    {
        $field = $this->getDefinition($entityClass)->getField($fieldName);

        if (! $field instanceof Field) {
            throw new InvalidEntityException(\sprintf('Field %s does not exist on entity %s', $fieldName, $entityClass));
        }

        return $field;
    }

    /**
     * Check if a field is an association.
     */
    public function isAssociation(string $entityClass, string $fieldName): bool
    {
        try {
            $field = $this->getField($entityClass, $fieldName);

            return $field instanceof AssociationField;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get association information.
     *
     * @throws InvalidEntityException
     *
     * @return array{propertyName: string, referenceClass: string, type: string}
     */
    public function getAssociationInfo(string $entityClass, string $fieldName): array
    {
        $field = $this->getField($entityClass, $fieldName);

        if (! $field instanceof AssociationField) {
            throw new InvalidEntityException(\sprintf('Field %s on entity %s is not an association', $fieldName, $entityClass));
        }

        if ($field instanceof ManyToManyAssociationField) {
            $definitionEntityClass = $field->getToManyReferenceDefinition()->getEntityClass();
        } else {
            $definitionEntityClass = $field->getReferenceDefinition()->getEntityClass();
        }

        $type = match (true) {
            $field instanceof ManyToOneAssociationField => 'many_to_one',
            $field instanceof OneToManyAssociationField => 'one_to_many',
            $field instanceof ManyToManyAssociationField => 'many_to_many',
            default => 'one_to_one',
        };

        return [
            'propertyName' => $field->getPropertyName(),
            'referenceClass' => $definitionEntityClass,
            'type' => $type,
        ];
    }

    /**
     * Get all available fields.
     *
     * @return string[]
     */
    public function getAvailableFields(string $entityClass): array
    {
        $definition = $this->getDefinition($entityClass);
        $fields = [];

        foreach ($definition->getFields() as $field) {
            $fields[] = $field->getPropertyName();
        }

        return $fields;
    }

    /**
     * Get all available associations.
     *
     * @return string[]
     */
    public function getAvailableAssociations(string $entityClass): array
    {
        $definition = $this->getDefinition($entityClass);
        $associations = [];

        foreach ($definition->getFields() as $field) {
            if ($field instanceof AssociationField) {
                $associations[] = $field->getPropertyName();
            }
        }

        return $associations;
    }
}
