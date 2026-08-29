<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version\Resolvers;

use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolverException;

final class ConfigKeyResolver implements VersionResolver
{
    /**
     * Create a configuration key resolver.
     */
    public function __construct(
        private readonly string $key,
    ) {}

    /**
     * Resolve the version from the configured Laravel key.
     */
    public function resolve(): ?string
    {
        $value = config($this->key);

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new VersionResolverException("Version configuration key [{$this->key}] must contain a string.");
        }

        return $value;
    }
}
