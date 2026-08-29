<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api\Routing;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Router;
use Infinity\Evolver\Api\ApiVersion;
use Infinity\Evolver\Api\ApiVersionRegistry;
use Infinity\Evolver\Api\Middleware\ResolveApiVersion;
use Infinity\Evolver\Exceptions\InvalidApiVersionException;

final readonly class ApiVersionRouteRegistrar
{
    /**
     * @param  array<string, mixed>  $versions
     */
    public function __construct(
        private Router $router,
        private ApiVersionRegistry $registry,
        private string $basePath,
        private array $versions,
    ) {}

    public function registerConfigured(): void
    {
        foreach ($this->versions as $value => $configuration) {
            if (! is_array($configuration) || ! isset($configuration['routes'])) {
                continue;
            }

            $middleware = $configuration['middleware'] ?? ['api'];

            if (! is_array($middleware)) {
                throw new InvalidApiVersionException("Middleware for API version [{$value}] must be an array.");
            }

            $this->routes(
                new ApiVersion($value),
                $configuration['routes'],
                array_values($middleware),
            );
        }

        $this->registerFallback();
    }

    /**
     * @param  Closure|non-empty-string  $routes
     * @param  list<mixed>  $middleware
     */
    public function routes(ApiVersion|string $version, Closure|string $routes, array $middleware = ['api']): void
    {
        $version = is_string($version) ? new ApiVersion($version) : $version;
        $this->registry->get($version);

        if (is_string($routes) && ! is_file($routes)) {
            throw new InvalidApiVersionException(
                "Route file [{$routes}] for API version [{$version->value}] does not exist.",
            );
        }

        $this->router->group([
            'prefix' => trim($this->basePath, '/').'/'.$version->value,
            'as' => "api.{$version->value}.",
            'middleware' => [...$middleware, ResolveApiVersion::class],
        ], $routes);
    }

    private function registerFallback(): void
    {
        $this->router->any(trim($this->basePath, '/').'/{api_version}/{path?}', static fn (): JsonResponse => new JsonResponse([
            'message' => 'API route not found.',
            'error' => ['code' => 'api_route_not_found'],
        ], 404))->where('path', '.*')->middleware(ResolveApiVersion::class);
    }
}
