<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidPropertyException;

/**
 * Resolves and validates property names using EntityDefinition metadata.
 *
 * Provides automatic validation with helpful error messages and support for nested properties.
 */
class PropertyResolver
{
    public function __construct(
        private readonly EntityDefinitionResolver $definitionResolver
    ) {}

    /**
     * Resolve and validate a property.
     *
     * @throws InvalidPropertyException
     */
    public function resolve(string $entityClass, string $property): string
    {
        // Handle nested properties (e.g., 'manufacturer.name')
        if (str_contains($property, '.')) {
            return $this->resolveNestedProperty($entityClass, $property);
        }

        // Validate that the property exists in the Definition
        if (! $this->definitionResolver->hasField($entityClass, $property)) {
            $available = $this->definitionResolver->getAvailableFields($entityClass);

            throw new InvalidPropertyException(sprintf("Property '%s' does not exist on %s. Available properties: %s%s", $property, $this->getShortClassName($entityClass), implode(', ', array_slice($available, 0, 10)), count($available) > 10 ? ', ...' : ''));
        }

        return $property;
    }

    /**
     * Resolve nested property with validation of each level.
     *
     * @throws InvalidPropertyException
     */
    private function resolveNestedProperty(string $entityClass, string $property): string
    {
        $parts = explode('.', $property);
        $currentEntityClass = $entityClass;
        $path = [];

        foreach ($parts as $index => $part) {
            $isLast = $index === count($parts) - 1;

            if ($isLast) {
                // Last part: must be a field
                if (! $this->definitionResolver->hasField($currentEntityClass, $part)) {
                    $available = $this->definitionResolver->getAvailableFields($currentEntityClass);

                    throw new InvalidPropertyException(sprintf("Property '%s' does not exist on %s (in nested path '%s'). Available: %s", $part, $this->getShortClassName($currentEntityClass), $property, implode(', ', array_slice($available, 0, 10))));
                }
                $path[] = $part;
            } else {
                // Intermediate part: must be an association
                if (! $this->definitionResolver->isAssociation($currentEntityClass, $part)) {
                    $available = $this->definitionResolver->getAvailableAssociations($currentEntityClass);

                    throw new InvalidPropertyException(sprintf("'%s' is not an association on %s (in nested path '%s'). Available associations: %s", $part, $this->getShortClassName($currentEntityClass), $property, implode(', ', $available)));
                }

                $associationInfo = $this->definitionResolver->getAssociationInfo($currentEntityClass, $part);
                $path[] = $associationInfo['propertyName'];
                $currentEntityClass = $associationInfo['referenceClass'];
            }
        }

        return implode('.', $path);
    }

    /**
     * Get short class name for better error messages.
     */
    private function getShortClassName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
