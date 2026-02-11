<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidParameterException;

/**
 * Parameter bag for managing query parameters.
 *
 * Handles named parameters (e.g., :status, :minPrice) for secure query building.
 * Implements SOLID principles:
 * - Single Responsibility: Manages only parameter storage and retrieval
 * - Open/Closed: Open for extension through inheritance
 * - Liskov Substitution: Can be replaced with compatible implementations
 * - Interface Segregation: Minimal, focused interface
 * - Dependency Inversion: Depends on abstractions, not concretions
 */
class ParameterBag
{
    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    /**
     * Set a named parameter.
     *
     * @param string $name Parameter name (without ':' prefix)
     * @param mixed $value Parameter value
     *
     * @throws InvalidParameterException If parameter name is invalid
     */
    public function set(string $name, mixed $value): self
    {
        $this->validateParameterName($name);

        $this->parameters[$this->normalizeParameterName($name)] = $value;

        return $this;
    }

    /**
     * Set multiple parameters at once.
     *
     * @param array<string, mixed> $parameters Map of parameter names to values
     *
     * @throws InvalidParameterException If any parameter name is invalid
     */
    public function setAll(array $parameters): self
    {
        foreach ($parameters as $name => $value) {
            $this->set($name, $value);
        }

        return $this;
    }

    /**
     * Get a parameter value.
     *
     * @param string $name Parameter name (with or without ':' prefix)
     *
     * @throws InvalidParameterException If parameter does not exist
     */
    public function get(string $name): mixed
    {
        $normalizedName = $this->normalizeParameterName($name);

        if (! $this->has($name)) {
            throw new InvalidParameterException(sprintf("Parameter ':%s' is not set. Available parameters: %s", $normalizedName, $this->getAvailableParametersString()));
        }

        return $this->parameters[$normalizedName];
    }

    /**
     * Check if a parameter exists.
     *
     * @param string $name Parameter name (with or without ':' prefix)
     */
    public function has(string $name): bool
    {
        return array_key_exists($this->normalizeParameterName($name), $this->parameters);
    }

    /**
     * Remove a parameter.
     *
     * @param string $name Parameter name (with or without ':' prefix)
     */
    public function remove(string $name): self
    {
        unset($this->parameters[$this->normalizeParameterName($name)]);

        return $this;
    }

    /**
     * Get all parameters.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * Clear all parameters.
     */
    public function clear(): self
    {
        $this->parameters = [];

        return $this;
    }

    /**
     * Count the number of parameters.
     */
    public function count(): int
    {
        return count($this->parameters);
    }

    /**
     * Normalize parameter name (remove ':' prefix if present).
     */
    private function normalizeParameterName(string $name): string
    {
        return ltrim($name, ':');
    }

    /**
     * Validate parameter name.
     *
     * @throws InvalidParameterException If name is invalid
     */
    private function validateParameterName(string $name): void
    {
        $normalizedName = $this->normalizeParameterName($name);

        if ($normalizedName === '') {
            throw new InvalidParameterException('Parameter name cannot be empty');
        }

        // Parameter names must be alphanumeric with underscores
        if (! preg_match('/^[a-zA-Z_]\w*$/', $normalizedName)) {
            throw new InvalidParameterException(sprintf("Invalid parameter name '%s'. Parameter names must start with a letter or underscore and contain only letters, numbers, and underscores.", $name));
        }
    }

    /**
     * Get string representation of available parameters.
     */
    private function getAvailableParametersString(): string
    {
        if ($this->parameters === []) {
            return 'none';
        }

        return implode(', ', array_map(
            static fn (string $name): string => ":{$name}",
            array_keys($this->parameters)
        ));
    }
}
