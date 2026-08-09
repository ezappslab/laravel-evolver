<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use JsonException;

final class JsonFileResolver implements VersionResolver
{
    /**
     * Create a JSON file resolver.
     */
    public function __construct(
        private readonly string $path,
        private readonly string $key,
    ) {}

    /**
     * Resolve the version from the JSON file.
     *
     * @throws VersionResolutionException|FileNotFoundException
     */
    public function resolve(): ?string
    {
        if (! File::exists($this->path)) {
            return null;
        }

        try {
            $data = File::json($this->path, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new VersionResolutionException("Invalid JSON in file: {$this->path}", $exception);
        }

        $value = data_get($data, $this->key);

        return is_string($value) ? $value : null;
    }
}
