<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidEntityException;
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
 * This approach eliminates manual configuration by leveraging Shopware's native EntityDefinition classes.
 * All property and association validation happens automatically at runtime.
 */
class EntityDefinitionResolver
{
    /**
     * @var array<string, EntityDefinition>
     */
    private array $cache = [];

    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {}

    /**
     * Get the EntityDefinition for a given Entity class.
     *
     * @throws InvalidEntityException
     */
    public function getDefinition(string $entityClass): EntityDefinition
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        // Shopware convention: ProductEntity -> ProductDefinition
        $definitionClass = str_replace('Entity', 'Definition', $entityClass);

        if (! class_exists($definitionClass)) {
            throw new InvalidEntityException(sprintf('Definition class %s not found for entity %s', $definitionClass, $entityClass));
        }

        if (! defined($definitionClass . '::ENTITY_NAME')) {
            throw new InvalidEntityException(sprintf('Definition class %s does not define ENTITY_NAME constant', $definitionClass));
        }

        $entityName = constant($definitionClass . '::ENTITY_NAME');

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

        $type = match (true) {
            $field instanceof ManyToOneAssociationField => 'many_to_one',
            $field instanceof OneToManyAssociationField => 'one_to_many',
            $field instanceof ManyToManyAssociationField => 'many_to_many',
            default => 'one_to_one',
        };

        return [
            'propertyName' => $field->getPropertyName(),
            'referenceClass' => $field->getReferenceDefinition()->getEntityClass(),
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
