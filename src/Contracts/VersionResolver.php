<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

use Infinity\Evolver\Exceptions\VersionResolverException;

interface VersionResolver
{
    /**
     * Resolve the configured application version, or null when it is absent.
     *
     * @throws VersionResolverException When the configured source cannot be read or is invalid.
     */
    public function resolve(): ?string;
}
