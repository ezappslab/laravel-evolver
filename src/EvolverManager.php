<?php

declare(strict_types=1);

namespace Infinity\Evolver;

use Illuminate\Http\Request;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Versioning\VersionManager;

/** Main public coordinator; business logic remains in its two managers. */
final readonly class EvolverManager
{
    /**
     * Create a new Evolver manager instance.
     */
    public function __construct(public DeploymentManager $deployment, public VersionManager $versions) {}

    /**
     * Resolve the current application version.
     *
     *
     * @throws VersionResolutionException
     */
    public function applicationVersion(): ?string
    {
        return $this->versions->application();
    }

    /**
     * Resolve the API version from the given or current request.
     *
     *
     * @throws VersionResolutionException
     */
    public function apiVersion(?Request $request = null): ?string
    {
        return $this->versions->api($request ?? request());
    }
}
