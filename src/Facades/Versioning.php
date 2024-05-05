<?php

namespace Infinity\Evolver\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * VersionTracking type.
 *
 * @method bool isEqual(string $version)
 * @method bool isNotEqual(string $version)
 * @method bool isLessThan(string $version)
 * @method bool isLessThanOrEqual(string $version)
 * @method bool isGreaterThan(string $version)
 * @method bool isGreaterThanOrEqual(string $version)
 */
class Versioning extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'versioning';
    }
}
