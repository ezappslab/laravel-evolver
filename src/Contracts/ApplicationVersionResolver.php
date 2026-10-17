<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

use Infinity\Evolver\Exceptions\VersionResolutionException;

/** Resolves the application's declared version, or null when none is declared. */
interface ApplicationVersionResolver
{
    /**
     * Resolve the application's version from the configured source.
     *
     * Returns the normalized source value, or null when none is declared.
     * Validating the version format is the responsibility of VersionManager.
     *
     *
     * @throws VersionResolutionException
     */
    public function resolve(): ?string;
}
