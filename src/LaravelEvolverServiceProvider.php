<?php

namespace Infinity\Evolver;

use Infinity\Evolver\Commands\Install;
use Infinity\Evolver\Commands\Upgrade;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelEvolverServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package service provider class.
     *
     * @param Package $package
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-evolver')
            ->hasCommands([
                Install::class,
                Upgrade::class,
            ])
            ->hasConfigFile()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->startWith(function (InstallCommand $command) {
                        $command->info('Publishing the configuration file...');
                    })
                    ->publishConfigFile()
                    ->endWith(function (InstallCommand $command) {
                        $command->info('Configuration file published');
                    });
            });
    }
}
