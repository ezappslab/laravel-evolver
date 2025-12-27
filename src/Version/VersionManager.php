<?php

namespace Infinity\Evolver\Version;

use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Contracts\Version;
use Infinity\Evolver\Exceptions\VersionResolutionException;

class VersionManager
{
    public function __construct(
        protected ActionRepository $repository,
        protected TargetVersionResolverFactory $factory
    ) {}

    /**
     * Get the current version.
     */
    public function current(): ?Version
    {
        $version = $this->repository->getCurrentVersion();

        return $version ? $this->parse($version) : null;
    }

    /**
     * Get the required target version.
     *
     * @throws VersionResolutionException
     */
    public function targetRequired(): ?Version
    {
        $resolverType = config('evolver.versioning.target.resolver');

        $resolver = $this->factory->make();

        $version = $resolver->resolve();

        if (blank($version)) {
            if (config('evolver.versioning.target.required', true)) {
                throw new VersionResolutionException("Unable to resolve target version using resolver: {$resolverType}");
            }

            return null;
        }

        return $this->parse($version);
    }

    /**
     * Parse the given version string.
     */
    public function parse(string $version): Version
    {
        $format = config('evolver.versioning.format', 'semver');

        if ($format === 'semver') {
            return SemanticVersion::parse($version);
        }

        throw new VersionResolutionException("Unsupported version format: {$format}");
    }
}
