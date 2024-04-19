<?php

namespace Infinity\Evolver;

use Infinity\Evolver\Commands\Install;
use Infinity\Evolver\Commands\MakeEvolverAction;
use Infinity\Evolver\Commands\Upgrade;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelEvolverServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package service provider class.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-evolver')
            ->hasCommands([
                Install::class,
                Upgrade::class,
                MakeEvolverAction::class,
            ])
            ->hasConfigFile()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command->publishConfigFile();
            });
    }
}
