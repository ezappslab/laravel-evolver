<?php

declare(strict_types=1);

namespace Infinity\Evolver\Versioning;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Infinity\Evolver\Contracts\ApiVersionResolver;
use Infinity\Evolver\Contracts\ApplicationVersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Routing\Resolvers\HeaderApiVersionResolver;
use Infinity\Evolver\Routing\Resolvers\UrlApiVersionResolver;
use Infinity\Evolver\Versioning\Resolvers\ConfigResolver;
use Infinity\Evolver\Versioning\Resolvers\FileResolver;
use Infinity\Evolver\Versioning\Resolvers\GitTagResolver;
use Infinity\Evolver\Versioning\Resolvers\JsonResolver;
use Infinity\Evolver\Versioning\Resolvers\NoneResolver;

/** Coordinates independent application and requested-API version resolvers. */
final class VersionManager
{
    /**
     * Create a new version manager instance.
     */
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve and validate the application's declared version.
     *
     *
     * @throws VersionResolutionException
     */
    public function application(): ?string
    {
        $version = $this->applicationResolver()->resolve();

        if ($version !== null && ! preg_match('/^v?\d+(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new VersionResolutionException('The resolved application version is invalid.');
        }

        return $version;
    }

    /**
     * Resolve the API version declared by the configured request strategy.
     *
     *
     * @throws VersionResolutionException
     */
    public function api(Request $request): ?string
    {
        return $this->apiResolver()->resolve($request);
    }

    /**
     * Build the configured application resolver or resolve a custom class.
     *
     * Custom resolvers are container-resolved so application dependencies can
     * be injected normally.
     *
     *
     * @throws VersionResolutionException
     */
    public function applicationResolver(): ApplicationVersionResolver
    {
        $name = config('evolver.versioning.application.resolver', 'none');

        if (is_string($name) && class_exists($name)) {
            $resolver = $this->container->make($name);

            if (! $resolver instanceof ApplicationVersionResolver) {
                throw new VersionResolutionException('The custom application resolver does not implement the required contract.');
            }

            return $resolver;
        }

        return match ($name) {
            'none' => new NoneResolver,
            'config' => new ConfigResolver(
                $this->container->make('config'),
                (string) config('evolver.versioning.application.resolvers.config.key', 'app.version'),
            ),
            'file' => new FileResolver(
                $this->container->make('files'),
                (string) config('evolver.versioning.application.resolvers.file.path', base_path('VERSION')),
            ),
            'json' => new JsonResolver(
                $this->container->make('files'),
                (string) config('evolver.versioning.application.resolvers.json.path', base_path('composer.json')),
                (string) config('evolver.versioning.application.resolvers.json.key', 'version'),
            ),
            'git' => new GitTagResolver(
                base_path(),
                (array) config('evolver.versioning.application.resolvers.git.prefixes', ['v', 'V']),
            ),
            default => throw new VersionResolutionException('Unknown application version resolver.'),
        };
    }

    /**
     * Build the API version resolver selected by configuration.
     *
     * The API resolver is selected independently from application versioning.
     *
     *
     * @throws VersionResolutionException
     */
    public function apiResolver(): ApiVersionResolver
    {
        return match (config('evolver.versioning.api.resolver', 'url')) {
            'url' => new UrlApiVersionResolver(
                (string) config('evolver.versioning.api.resolvers.url.base_path', 'api'),
            ),
            'header' => new HeaderApiVersionResolver(
                (string) config('evolver.versioning.api.resolvers.header.name', 'X-API-Version'),
            ),
            default => throw new VersionResolutionException('Unknown API version resolver.'),
        };
    }
}
