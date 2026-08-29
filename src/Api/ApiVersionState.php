<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api;

enum ApiVersionState: string
{
    case Active = 'active';
    case Deprecated = 'deprecated';
    case Sunset = 'sunset';
}
