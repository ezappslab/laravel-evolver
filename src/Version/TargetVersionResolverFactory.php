<?php

namespace Infinity\Evolver\Version;

use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Version\Resolvers\ConfigKeyResolver;
use Infinity\Evolver\Version\Resolvers\GitTagResolver;
use Infinity\Evolver\Version\Resolvers\JsonFileResolver;
use Infinity\Evolver\Version\Resolvers\VersionFileResolver;

class TargetVersionResolverFactory
{
    /**
     * Create a version resolver instance based on the configuration.
     *
     * @throws VersionResolutionException
     */
    public function make(): VersionResolver
    {
        $resolverType = config('evolver.versioning.target.resolver');

        return match ($resolverType) {
            'file' => new VersionFileResolver(
                config('evolver.versioning.target.file.path') ?? ''
            ),
            'config' => new ConfigKeyResolver(
                config('evolver.versioning.target.config.key') ?? ''
            ),
            'json' => new JsonFileResolver(
                config('evolver.versioning.target.json.path') ?? '',
                config('evolver.versioning.target.json.key') ?? ''
            ),
            'git' => new GitTagResolver(
                config('evolver.versioning.target.git.strip_prefix') ?? 'v'
            ),
            default => throw new VersionResolutionException("Unknown version resolver: {$resolverType}"),
        };
    }
}
