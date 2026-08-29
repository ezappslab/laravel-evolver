<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolverException;
use Throwable;

final class VersionFileResolver implements VersionResolver
{
    /**
     * Create a plain-text version file resolver.
     */
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * Resolve the version from the file.
     */
    public function resolve(): ?string
    {
        if (! $this->sourceExists()) {
            return null;
        }

        try {
            $content = str(File::get($this->path))->trim();
        } catch (Throwable $exception) {
            if (! $this->sourceExists()) {
                return null;
            }

            throw new VersionResolverException("Unable to read version file: {$this->path}", $exception);
        }

        return $content->isNotEmpty() ? $content->value() : null;
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
