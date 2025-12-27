<?php

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;

class JsonFileResolver implements VersionResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(
        protected string $path,
        protected string $key
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

        $content = File::get($this->path);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new VersionResolutionException("Invalid JSON in file: {$this->path}");
        }

        $value = data_get($data, $this->key);

        return is_string($value) ? $value : null;
    }
}
