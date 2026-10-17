<?php

declare(strict_types=1);

namespace Infinity\Evolver\Versioning\Resolvers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\ApplicationVersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;

/** Reads an application version from Laravel's configuration repository. */
final class ConfigResolver implements ApplicationVersionResolver
{
    /**
     * Create a new config version resolver instance.
     */
    public function __construct(private readonly Repository $config, private readonly string $key) {}

    /**
     * Resolve the application version from the configured key.
     *
     * Null and empty string values are treated as absent.
     *
     *
     * @throws VersionResolutionException
     */
    public function resolve(): ?string
    {
        $value = $this->config->get($this->key);
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            throw new VersionResolutionException("Configuration [{$this->key}] must contain a version string.");
        }

        return Str::trim((string) $value);
    }
}
