<?php

namespace Infinity\Evolver\Version\Resolvers;

use Infinity\Evolver\Contracts\VersionResolver;

class ConfigKeyResolver implements VersionResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(
        protected string $key
    ) {}

    /**
     * Resolve the version from the configuration.
     */
    public function resolve(): ?string
    {
        $value = config($this->key);

        return is_string($value) ? $value : null;
    }
}
