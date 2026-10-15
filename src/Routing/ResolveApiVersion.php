<?php

declare(strict_types=1);

namespace Infinity\Evolver\Routing;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Infinity\Evolver\Exceptions\UnsupportedApiVersionException;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Versioning\VersionManager;
use Symfony\Component\HttpFoundation\Response;

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
     * Header mode rebuilds the Symfony request using the internal path while
     * preserving its method, headers, query, body, cookies, files, and request
     * attributes. The original URI remains available to downstream code.
     *
     *
     * @throws UnsupportedApiVersionException
     * @throws VersionResolutionException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $base = $this->versions->apiBasePath();
        $path = trim($request->path(), '/');
        if ($path !== $base && ! str_starts_with($path, $base.'/')) {
            return $next($request);
        }

        $resolver = (string) config('evolver.versioning.api.resolver', 'url');
        $version = $this->versions->api($request);
        $supported = array_values((array) config('evolver.versioning.api.versions', []));
        if ($resolver === 'header') {
            $first = explode('/', substr($path, strlen($base) + 1))[0];
            if (in_array($first, $supported, true)) {
                return response('Not Found', 404);
            }

            if ($version === null) {
                return response()->json(['message' => 'API version header is required.'], 400);
            }

            $this->validate($version, $supported);
            $suffix = substr($path, strlen($base));
            $query = $request->getQueryString();
            $external = $request->getRequestUri();
            $internal = '/'.$base.'/'.$version.$suffix.($query === null ? '' : '?'.$query);
            $server = array_replace($request->server->all(), ['REQUEST_URI' => $internal, 'PATH_INFO' => '/'.$base.'/'.$version.$suffix]);
            $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $server, $request->getContent());
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
        if (! Str::isMatch('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $version) || ($supported !== [] && ! in_array($version, $supported, true))) {
            throw new UnsupportedApiVersionException('The requested API version is unsupported.');
        }
    }
}
