<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidPropertyException;

/**
 * Resolves and validates association names using EntityDefinition metadata.
 *
 * Supports nested associations (e.g., 'manufacturer.country') with full validation at each level.
 */
class AssociationResolver
{
    public function __construct(
        private readonly EntityDefinitionResolver $definitionResolver
    ) {}

    /**
     * Resolve and validate an association.
     *
     * @throws InvalidPropertyException
     *
     * @return array{path: string, entity: string, propertyName: string}
     */
    public function resolve(string $entityClass, string $association): array
    {
        // Handle nested associations (e.g., 'manufacturer.country')
        if (str_contains($association, '.')) {
            return $this->resolveNestedAssociation($entityClass, $association);
        }

        // Validate that the association exists
        if (! $this->definitionResolver->isAssociation($entityClass, $association)) {
            $available = $this->definitionResolver->getAvailableAssociations($entityClass);

            throw new InvalidPropertyException(sprintf("Association '%s' does not exist on %s. Available associations: %s", $association, $this->getShortClassName($entityClass), implode(', ', $available)));
        }

        $info = $this->definitionResolver->getAssociationInfo($entityClass, $association);

        return [
            'path' => $info['propertyName'],
            'entity' => $info['referenceClass'],
            'propertyName' => $info['propertyName'],
        ];
    }

    /**
     * Resolve nested association with validation at each level.
     *
     * @throws InvalidPropertyException
     *
     * @return array{path: string, entity: string, propertyName: string}
     */
    private function resolveNestedAssociation(string $entityClass, string $association): array
    {
        $parts = explode('.', $association);
        $currentEntityClass = $entityClass;
        $path = [];
        $lastPropertyName = '';

        foreach ($parts as $part) {
            if (! $this->definitionResolver->isAssociation($currentEntityClass, $part)) {
                $available = $this->definitionResolver->getAvailableAssociations($currentEntityClass);

                throw new InvalidPropertyException(sprintf("Association '%s' does not exist on %s (in nested path '%s'). Available associations: %s", $part, $this->getShortClassName($currentEntityClass), $association, implode(', ', $available)));
            }

            $info = $this->definitionResolver->getAssociationInfo($currentEntityClass, $part);
            $path[] = $info['propertyName'];
            $lastPropertyName = $info['propertyName'];
            $currentEntityClass = $info['referenceClass'];
        }

        return [
            'path' => implode('.', $path),
            'entity' => $currentEntityClass,
            'propertyName' => $lastPropertyName,
        ];
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
