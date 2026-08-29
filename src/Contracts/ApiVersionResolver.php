<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

use Illuminate\Http\Request;
use Infinity\Evolver\Api\ApiVersion;

interface ApiVersionResolver
{
    public function resolve(Request $request): ?ApiVersion;
}
