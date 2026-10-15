<?php

declare(strict_types=1);

namespace Infinity\Evolver\Routing\Resolvers;

use Illuminate\Http\Request;
use Infinity\Evolver\Contracts\ApiVersionResolver;

/** Extracts the first path segment following the configured API base path. */
final class UrlApiVersionResolver implements ApiVersionResolver
{
    /**
     * Create a new URL API version resolver instance.
     */
    public function __construct(private readonly string $basePath = 'api') {}

    /**
     * Resolve the requested API version from the request path.
     *
     * Paths such as "api/v2/products" resolve to the version segment "v2".
     */
    public function resolve(Request $request): ?string
    {
        $pattern = '#^'.preg_quote(trim($this->basePath, '/'), '#').'/([^/]+)(?:/|$)#';

        return preg_match($pattern, trim($request->path(), '/'), $matches) === 1 ? $matches[1] : null;
    }
}
