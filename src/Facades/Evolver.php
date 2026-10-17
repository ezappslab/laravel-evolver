<?php

declare(strict_types=1);

namespace Infinity\Evolver\Facades;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Infinity\Evolver\Deployment\Action;
use Infinity\Evolver\EvolverManager;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Testing\EvolverFake;

/**
 * Laravel facade for Evolver's lightweight public manager.
 *
 * @method static ?string applicationVersion()
 * @method static ?string apiVersion(?\Illuminate\Http\Request $request = null)
 */
final class Evolver extends Facade
{
    /**
     * Replace the action source with in-memory actions keyed by identifier.
     *
     * @param  array<string, Action>  $actions
     *
     * @throws InvalidActionException
     */
    public static function fake(array $actions = []): EvolverFake
    {
        return EvolverFake::install(Container::getInstance(), $actions);
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return EvolverManager::class;
    }
}
