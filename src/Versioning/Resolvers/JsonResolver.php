<?php

declare(strict_types=1);

namespace Infinity\Evolver\Versioning\Resolvers;

use Illuminate\Filesystem\Filesystem;
use Infinity\Evolver\Contracts\ApplicationVersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;

/** Resolves a dotted key from a JSON document using Laravel's data_get helper. */
final class JsonResolver implements ApplicationVersionResolver
{
    /**
     * Create a new JSON version resolver instance.
     */
    public function __construct(private readonly Filesystem $files, private readonly string $path, private readonly string $key) {}

    /**
     * Resolve the application version from the configured JSON key.
     *
     *
     * @throws VersionResolutionException
     */
    public function resolve(): string
    {
        if (! $this->files->exists($this->path)) {
            throw new VersionResolutionException('Configured JSON version file is missing.');
        }

        try {
            $data = json_decode($this->files->get($this->path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new VersionResolutionException('Configured JSON version file is malformed.', 0, $e);
        }

        $value = data_get($data, $this->key);
        if (! is_string($value) && ! is_numeric($value)) {
            throw new VersionResolutionException("JSON version key [{$this->key}] is missing or invalid.");
        }

        return trim((string) $value);
    }
}
