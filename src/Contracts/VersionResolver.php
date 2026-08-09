<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

interface VersionResolver
{
    /**
     * Resolve the configured application version.
     */
    public function resolve(): ?string;
}
