<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version\Resolvers;

use Infinity\Evolver\Contracts\VersionResolver;

final class ConfigKeyResolver implements VersionResolver
{
    /**
     * Create a configuration key resolver.
     */
    public function __construct(
        protected string $key,
    ) {}

    /**
     * Resolve the version from the configured Laravel key.
     */
    public function resolve(): ?string
    {
        $value = config($this->key);

        return is_string($value) ? $value : null;
    }
}
