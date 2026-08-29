<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

use Infinity\Evolver\Api\ApiVersion;

final class UnsupportedApiVersionException extends EvolverException
{
    public function __construct(public readonly ApiVersion $version)
    {
        parent::__construct("Unsupported API version [{$version->value}].");
    }
}
