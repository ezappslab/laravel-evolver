<?php

namespace Infinity\Evolver;

use Infinity\Evolver\Commands\Install;
use Infinity\Evolver\Commands\Upgrade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelEvolverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-evolver')
            ->hasCommands([
                Install::class,
                Upgrade::class,
            ])
            ->hasConfigFile();
    }
}
