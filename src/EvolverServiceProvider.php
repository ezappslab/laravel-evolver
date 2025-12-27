<?php

namespace Infinity\Evolver;

use Infinity\Evolver\Commands\ActionMakeCommand;
use Infinity\Evolver\Commands\DeployCommand;
use Infinity\Evolver\Commands\StatusCommand;
use Infinity\Evolver\Contracts\ActionRepository as ActionRepositoryContract;
use Infinity\Evolver\Contracts\VersionResolver as VersionResolverContract;
use Infinity\Evolver\Database\ActionRepository;
use Infinity\Evolver\Deploy\Planning\ActionDiscovery;
use Infinity\Evolver\Deploy\Planning\ActionMaterializer;
use Infinity\Evolver\Deploy\Planning\ApplicabilityPolicy;
use Infinity\Evolver\Deploy\Running\ActionRunner;
use Infinity\Evolver\Version\TargetVersionResolverFactory;
use Infinity\Evolver\Version\VersionManager;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EvolverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-evolver')
            ->hasConfigFile('evolver')
            ->hasMigration('create_evolver_tables')
            ->hasCommands([
                ActionMakeCommand::class,
                DeployCommand::class,
                StatusCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    /**
     * Registers the package by binding the ActionRepositoryContract interface
     * to its concrete implementation, ActionRepository, within the service container.
     */
    public function packageRegistered(): void
    {
        $this->app->bind(ActionRepositoryContract::class, ActionRepository::class);

        $this->app->bind(ActionDiscovery::class, function ($app) {
            return new ActionDiscovery(
                config('evolver.actions_path', base_path('deploy/actions'))
            );
        });

        $this->app->bind(ApplicabilityPolicy::class, function ($app) {
            return ApplicabilityPolicy::fromConfig();
        });

        $this->app->bind(ActionRunner::class, function ($app) {
            return ActionRunner::fromConfig(
                $app->make(ActionRepositoryContract::class),
                $app->make(ActionMaterializer::class)
            );
        });

        $this->app->singleton(TargetVersionResolverFactory::class);

        $this->app->bind(VersionResolverContract::class, function ($app) {
            return $app->make(TargetVersionResolverFactory::class)->make();
        });

        $this->app->singleton(VersionManager::class);
    }
}
