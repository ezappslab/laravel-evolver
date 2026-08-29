<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolverException;
use JsonException;
use Throwable;

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
     * @throws VersionResolverException
     */
    public function resolve(): ?string
    {
        if (! $this->sourceExists()) {
            return null;
        }

        try {
            $contents = File::get($this->path);
        } catch (Throwable $exception) {
            if (! $this->sourceExists()) {
                return null;
            }

            throw new VersionResolverException("Unable to read JSON version file: {$this->path}", $exception);
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new VersionResolverException("Invalid JSON in file: {$this->path}", $exception);
        }

        $value = data_get($data, $this->key);

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new VersionResolverException(
                "Version key [{$this->key}] in JSON file [{$this->path}] must contain a string.",
            );
        }

        return $value;
    }

    /**
     * Determine whether the configured source currently exists.
     *
     * @phpstan-impure
     */
    private function sourceExists(): bool
    {
        return File::exists($this->path);
    }
}
