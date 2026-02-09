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

        $entityName = $this->resolveEntityName($entityClass);

        try {
            $definition = $this->definitionRegistry->getByEntityName($entityName);
        } catch (\Exception $e) {
            throw new InvalidEntityException(sprintf('Could not resolve definition for entity %s: %s', $entityClass, $e->getMessage()), 0, $e);
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
            throw new InvalidEntityException(sprintf('Field %s does not exist on entity %s', $fieldName, $entityClass));
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
            throw new InvalidEntityException(sprintf('Field %s on entity %s is not an association', $fieldName, $entityClass));
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

    /**
     * Resolve the entity name from the Entity class.
     *
     * Tries multiple strategies:
     * 1. Custom mapping via registerEntityMapping()
     * 2. Convention-based (Entity -> Definition)
     * 3. Reflection-based (PHP 8 attributes)
     *
     * @throws InvalidEntityException
     */
    private function resolveEntityName(string $entityClass): string
    {
        // Custom mapping
        if (isset($this->entityNameMapping[$entityClass])) {
            return $this->entityNameMapping[$entityClass];
        }

        // Convention-based
        $definitionClass = str_replace('Entity', 'Definition', $entityClass);
        if (class_exists($definitionClass) && defined($definitionClass . '::ENTITY_NAME')) {
            return constant($definitionClass . '::ENTITY_NAME');
        }

        // Reflection-based (Attribute-based entities)
        $entityName = $this->resolveEntityNameViaReflection($entityClass);
        if ($entityName !== null) {
            // Cache the discovered mapping for future use
            $this->entityNameMapping[$entityClass] = $entityName;

            return $entityName;
        }

        throw new InvalidEntityException(sprintf('Could not resolve entity name for %s. Definition class %s not found. Please register via registerEntityMapping() or ensure class follows Entity->Definition convention.', $entityClass, $definitionClass));
    }

    /**
     * Try to resolve entity name via PHP 8 attributes.
     *
     * Looks for #[Entity(name: 'entity_name')] or similar Shopware entity attributes.
     * This enables support for attribute-based entities without explicit registration.
     *
     * @return string|null The entity name if found via attributes, null otherwise
     */
    private function resolveEntityNameViaReflection(string $entityClass): ?string
    {
        if (! class_exists($entityClass)) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass($entityClass);
            $attributes = $reflection->getAttributes();

            foreach ($attributes as $attribute) {
                $attributeName = $attribute->getName();

                // Check if it's a Shopware Entity attribute (any attribute containing 'Entity')
                if ($attributeName === Entity::class) {
                    $args = $attribute->getArguments();

                    if (isset($args['name'])) {
                        return $args['name'];
                    }

                    // Try positional argument (first parameter)
                    if (isset($args[0])) {
                        return $args[0];
                    }
                }
            }
        } catch (\Exception) {
            // Silently ignore reflection errors and fall through
        }

        return null;
    }
}
