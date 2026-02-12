<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidAliasException;

/**
 * Centralized alias resolution with validation and caching.
 */
class AliasResolver
{
    /**
     * @var array<string, string> Map of alias => association path
     */
    private array $aliasMap = [];

    private ?string $mainEntityAlias = null;

    /**
     * Register an alias for an association path.
     *
     * @throws InvalidAliasException
     */
    public function register(string $alias, string $associationPath): void
    {
        // Validate alias format
        if (! preg_match('/^[a-zA-Z_]\w*$/', $alias)) {
            throw new InvalidAliasException("Invalid alias '{$alias}'. Must start with letter/underscore and contain only alphanumeric characters.");
        }

        // Check for conflicts
        if (isset($this->aliasMap[$alias])) {
            throw new InvalidAliasException("Alias '{$alias}' is already registered for path '{$this->aliasMap[$alias]}'");
        }

        $this->aliasMap[$alias] = $associationPath;
    }

    /**
     * Set alias for main entity.
     */
    public function setMainEntityAlias(string $alias): void
    {
        if (isset($this->aliasMap[$alias])) {
            throw new InvalidAliasException("Cannot use '{$alias}' as main entity alias - already registered for '{$this->aliasMap[$alias]}'");
        }

        $this->mainEntityAlias = $alias;
    }

    /**
     * Resolve a field reference to its full path.
     *
     * Returns array with:
     * - 'path': The resolved path
     * - 'type': 'direct', 'alias', 'main_alias', or 'nested'
     * - 'original': Original input
     *
     * @return array{path: string, type: string, original: string}
     */
    public function resolve(string $field): array
    {
        // Simple property without dots
        if (! str_contains($field, '.')) {
            return [
                'path' => $field,
                'type' => 'direct',
                'original' => $field,
            ];
        }

        // Split into parts
        $parts = explode('.', $field, 2);
        $firstPart = $parts[0];
        $remainingPath = $parts[1];

        // Check if it's a registered alias
        if (isset($this->aliasMap[$firstPart])) {
            return [
                'path' => $this->aliasMap[$firstPart] . '.' . $remainingPath,
                'type' => 'alias',
                'original' => $field,
            ];
        }

        // Check if it's the main entity alias
        if ($this->mainEntityAlias !== null && $firstPart === $this->mainEntityAlias) {
            return [
                'path' => $remainingPath,
                'type' => 'main_alias',
                'original' => $field,
            ];
        }

        // Treat as nested property (e.g., 'manufacturer.name')
        return [
            'path' => $field,
            'type' => 'nested',
            'original' => $field,
        ];
    }

    /**
     * Check if an alias is registered.
     */
    public function hasAlias(string $alias): bool
    {
        return isset($this->aliasMap[$alias])
            || ($this->mainEntityAlias !== null && $alias === $this->mainEntityAlias);
    }

    /**
     * Get all registered aliases.
     *
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliasMap;
    }

    /**
     * Clone alias map to another resolver (for sub-queries).
     */
    public function copyTo(AliasResolver $target): void
    {
        $target->aliasMap = $this->aliasMap;
        $target->mainEntityAlias = $this->mainEntityAlias;
    }

    /**
     * Clear all aliases.
     */
    public function clear(): void
    {
        $this->aliasMap = [];
        $this->mainEntityAlias = null;
    }
}
