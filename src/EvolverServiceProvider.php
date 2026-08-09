<?php

declare(strict_types=1);

namespace Infinity\Evolver;

use Infinity\Evolver\Commands\ActionMakeCommand;
use Infinity\Evolver\Commands\DeployCommand;
use Infinity\Evolver\Commands\StatusCommand;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Database\DatabaseEvolutionRepository;
use Infinity\Evolver\Deploy\Planning\ActionDiscovery;
use Infinity\Evolver\Deploy\Planning\ActionMaterializer;
use Infinity\Evolver\Deploy\Planning\Planner;
use Infinity\Evolver\Deploy\Running\Runner;
use Infinity\Evolver\Deploy\Running\TransactionMode;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Version\Resolvers\ConfigKeyResolver;
use Infinity\Evolver\Version\Resolvers\GitTagResolver;
use Infinity\Evolver\Version\Resolvers\JsonFileResolver;
use Infinity\Evolver\Version\Resolvers\VersionFileResolver;
use Infinity\Evolver\Version\VersionManager;
use Infinity\Evolver\Version\VersionStrategy;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class EvolverServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package resources and Artisan commands.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-evolver')
            ->hasConfigFile('evolver')
            ->hasMigration('create_evolver_tables')
            ->hasCommands([ActionMakeCommand::class, DeployCommand::class, StatusCommand::class])
            ->hasInstallCommand(fn (InstallCommand $command) => $command
                ->publishConfigFile()
                ->publishMigrations()
                ->askToRunMigrations());
    }

    /**
     * Register the package services in the container.
     */
    public function packageRegistered(): void
    {
        $this->app->bind(EvolutionRepository::class, DatabaseEvolutionRepository::class);
        $this->app->bind(ActionDiscovery::class, fn (): ActionDiscovery => new ActionDiscovery(
            (string) config('evolver.actions_path', base_path('deploy/actions')),
        ));

        $this->app->singleton(VersionManager::class, function (): VersionManager {
            $configured = config('evolver.versioning.strategy', VersionStrategy::None);
            $strategy = $configured instanceof VersionStrategy
                ? $configured
                : VersionStrategy::tryFrom((string) $configured);

            if ($strategy === null) {
                throw new VersionResolutionException("Unknown version strategy: {$configured}");
            }

            return new VersionManager(
                $strategy,
                $this->resolver($strategy),
                (bool) config('evolver.versioning.required', true),
            );
        });

        $this->app->bind(Planner::class, fn ($app): Planner => new Planner(
            $app->make(ActionDiscovery::class),
            $app->make(ActionMaterializer::class),
            $app->make(EvolutionRepository::class),
            $app->make(VersionManager::class),
            (bool) config('evolver.safety.fail_on_changed_action', true),
        ));

        $this->app->bind(Runner::class, function ($app): Runner {
            $configured = config('evolver.transactions.mode', TransactionMode::PerAction);
            $mode = $configured instanceof TransactionMode
                ? $configured
                : TransactionMode::tryFrom((string) $configured);

            if ($mode === null) {
                throw new \InvalidArgumentException("Unknown transaction mode: {$configured}");
            }

            return new Runner(
                $app->make(EvolutionRepository::class),
                $mode,
            );
        });
    }

    /**
     * Build the resolver required by the selected version strategy.
     */
    private function resolver(VersionStrategy $strategy): ?VersionResolver
    {
        return match ($strategy) {
            VersionStrategy::None => null,
            VersionStrategy::File => new VersionFileResolver((string) config('evolver.versioning.file.path', '')),
            VersionStrategy::Config => new ConfigKeyResolver((string) config('evolver.versioning.config.key', '')),
            VersionStrategy::Json => new JsonFileResolver(
                (string) config('evolver.versioning.json.path', ''),
                (string) config('evolver.versioning.json.key', ''),
            ),
            VersionStrategy::Git => new GitTagResolver((string) config('evolver.versioning.git.strip_prefix', 'v')),
        };
    }
}
