<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\VersionResolver;

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
        if (! File::exists($this->path)) {
            return null;
        }

        $content = str(File::get($this->path))->trim();

        return $content->isNotEmpty() ? $content->value() : null;
    }
}
