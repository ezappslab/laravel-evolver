<?php

declare(strict_types=1);

namespace Infinity\Evolver\Routing\Resolvers;

use Illuminate\Http\Request;
use Infinity\Evolver\Contracts\ApiVersionResolver;

/** Extracts the requested version from a configurable HTTP header. */
final class HeaderApiVersionResolver implements ApiVersionResolver
{
    /**
     * Create a new header API version resolver instance.
     */
    public function __construct(private readonly string $header = 'X-API-Version') {}

    /**
     * Resolve the requested API version from the configured header.
     *
     * Returns the trimmed header value, or null when it is absent or blank.
     */
    public function resolve(Request $request): ?string
    {
        $value = $request->headers->get($this->header);

        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
