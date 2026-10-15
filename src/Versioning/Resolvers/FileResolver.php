<?php

declare(strict_types=1);

namespace Infinity\Evolver\Versioning\Resolvers;

use Illuminate\Filesystem\Filesystem;
use Infinity\Evolver\Contracts\ApplicationVersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;

/** Reads a trimmed application version from a dedicated text file. */
final class FileResolver implements ApplicationVersionResolver
{
    /**
     * Create a new file version resolver instance.
     */
    public function __construct(private readonly Filesystem $files, private readonly string $path) {}

    /**
     * Resolve the application version from the configured file.
     *
     * An empty file resolves no version.
     *
     *
     * @throws VersionResolutionException
     */
    public function resolve(): ?string
    {
        if (! $this->files->exists($this->path)) {
            throw new VersionResolutionException('Configured version file is missing.');
        }

        $value = trim($this->files->get($this->path));

        return $value === '' ? null : $value;
    }
}
