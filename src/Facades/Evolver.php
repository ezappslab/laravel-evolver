<?php

declare(strict_types=1);

namespace Infinity\Evolver\Facades;

use Illuminate\Support\Facades\Facade;
use Infinity\Evolver\EvolverManager;

/**
 * Laravel facade for Evolver's lightweight public manager.
 *
 * @method static ?string applicationVersion()
 * @method static ?string apiVersion(?\Illuminate\Http\Request $request = null)
 */
final class Evolver extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return EvolverManager::class;
    }
}
