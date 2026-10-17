<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

use Illuminate\Http\Request;

/** Extracts an API version from an incoming request without dispatching it. */
interface ApiVersionResolver
{
    /**
     * Resolve the requested API version from the given request.
     *
     * Returns null when the request does not declare a version.
     */
    public function resolve(Request $request): ?string;
}
