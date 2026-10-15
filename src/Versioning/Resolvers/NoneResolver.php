<?php

declare(strict_types=1);

namespace Infinity\Evolver\Versioning\Resolvers;

use Infinity\Evolver\Contracts\ApplicationVersionResolver;

/** Disables application-version filtering without disabling deployments. */
final class NoneResolver implements ApplicationVersionResolver
{
    /**
     * Report that the application has no declared version.
     *
     * @return null
     */
    public function resolve(): ?string
    {
        return null;
    }
}
