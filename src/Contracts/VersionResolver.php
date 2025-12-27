<?php

namespace Infinity\Evolver\Contracts;

interface VersionResolver
{
    /**
     * Resolve the current version.
     */
    public function resolve(): ?string;
}
