<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api\Resolvers;

use Illuminate\Http\Request;
use Infinity\Evolver\Api\ApiVersion;
use Infinity\Evolver\Contracts\ApiVersionResolver;

final readonly class UrlApiVersionResolver implements ApiVersionResolver
{
    public function __construct(private string $basePath = 'api') {}

    public function resolve(Request $request): ?ApiVersion
    {
        $segments = $request->segments();
        $baseSegments = array_values(array_filter(explode('/', trim($this->basePath, '/'))));

        if (array_slice($segments, 0, count($baseSegments)) !== $baseSegments) {
            return null;
        }

        $value = $segments[count($baseSegments)] ?? null;

        return is_string($value) ? new ApiVersion($value) : null;
    }
}
