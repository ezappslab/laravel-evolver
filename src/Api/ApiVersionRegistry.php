<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api;

use DateTimeImmutable;
use Infinity\Evolver\Exceptions\InvalidApiVersionException;

final class ApiVersionRegistry
{
    /** @var array<string, ApiVersionDefinition> */
    private array $definitions = [];

    /**
     * @param  list<ApiVersionDefinition>  $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $definition) {
            $key = $definition->version->value;

            if (isset($this->definitions[$key])) {
                throw new InvalidApiVersionException("API version [{$key}] is defined more than once.");
            }

            $this->definitions[$key] = $definition;
        }

        foreach ($this->definitions as $definition) {
            if ($definition->successor !== null && ! isset($this->definitions[$definition->successor->value])) {
                throw new InvalidApiVersionException(
                    "Successor API version [{$definition->successor->value}] is not registered.",
                );
            }
        }
    }

    public function find(ApiVersion $version): ?ApiVersionDefinition
    {
        return $this->definitions[$version->value] ?? null;
    }

    public function get(ApiVersion $version): ApiVersionDefinition
    {
        return $this->find($version)
            ?? throw new InvalidApiVersionException("Unsupported API version [{$version->value}].");
    }

    public function state(ApiVersion $version, ?DateTimeImmutable $at = null): ApiVersionState
    {
        return $this->get($version)->lifecycle->stateAt($at ?? new DateTimeImmutable);
    }

    /** @return list<ApiVersionDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
