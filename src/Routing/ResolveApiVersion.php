<?php

declare(strict_types=1);

namespace Infinity\Evolver\Routing;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Infinity\Evolver\Exceptions\MissingApiVersionException;
use Infinity\Evolver\Exceptions\UnsupportedApiVersionException;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Versioning\VersionManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Rewrites only configured header-versioned API paths before route matching. */
final class ResolveApiVersion
{
    /**
     * Create a new API version resolution middleware instance.
     */
    public function __construct(private readonly VersionManager $versions, private readonly ApiVersionContext $context) {}

    /**
     * Resolve API version information before Laravel matches a route.
     *
     * Header mode passes a duplicate of the request with the internal path to
     * the next middleware, preserving its method, headers, query, cookies,
     * files, and request attributes. The body is shared rather than read, so
     * streamed payloads are not buffered. The original URI remains available
     * to downstream code.
     * Rejections are thrown as HTTP exceptions so the application's exception
     * handler renders them consistently.
     *
     *
     * @throws MissingApiVersionException
     * @throws NotFoundHttpException
     * @throws UnsupportedApiVersionException
     * @throws VersionResolutionException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->set(null);

        $base = $this->versions->apiBasePath();
        $path = Str::trim($request->path(), '/');
        if ($path !== $base && ! Str::startsWith($path, $base.'/')) {
            return $next($request);
        }

        $resolver = (string) config('evolver.versioning.api.resolver', 'url');
        $version = $this->versions->api($request);
        $supported = collect((array) config('evolver.versioning.api.versions', []))->values()->all();
        if ($resolver === 'header') {
            $first = Str::before(Str::substr($path, Str::length($base) + 1), '/');
            if (collect($supported)->containsStrict($first)) {
                throw new NotFoundHttpException;
            }

            if ($version === null) {
                throw new MissingApiVersionException('The API version header is required.');
            }

            $this->validate($version, $supported);
            $suffix = Str::substr($path, Str::length($base));
            $query = $request->getQueryString();
            $external = $request->getRequestUri();
            $internal = '/'.$base.'/'.$version.$suffix.($query === null ? '' : '?'.$query);
            $server = $request->server->all();
            Arr::set($server, 'REQUEST_URI', $internal);
            Arr::set($server, 'PATH_INFO', '/'.$base.'/'.$version.$suffix);
            $request = $request->duplicate(server: $server);
            $request->attributes->set('_evolver_external_uri', $external);
            $request->attributes->set('_evolver_api_version', $version);
        } elseif ($version !== null) {
            $this->validate($version, $supported);
            $request->attributes->set('_evolver_api_version', $version);
        }

        $this->context->set($version);

        return $next($request);
    }

    /**
     * Reject unsafe version segments and versions outside the configured set.
     *
     * @param  list<mixed>  $supported
     *
     * @throws UnsupportedApiVersionException
     */
    private function validate(string $version, array $supported): void
    {
        if (! Str::isMatch('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $version) || ($supported !== [] && ! collect($supported)->containsStrict($version))) {
            throw new UnsupportedApiVersionException('The requested API version is unsupported.');
        }
    }
}
