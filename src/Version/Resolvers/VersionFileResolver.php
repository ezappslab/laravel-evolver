<?php

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\VersionResolver;

class VersionFileResolver implements VersionResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(
        protected string $path
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
