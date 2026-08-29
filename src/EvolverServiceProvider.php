<?php

declare(strict_types=1);

namespace Infinity\Evolver;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Router;
use Infinity\Evolver\Api\ApiVersionContext;
use Infinity\Evolver\Api\ApiVersionContextStore;
use Infinity\Evolver\Api\ApiVersionRegistry;
use Infinity\Evolver\Api\ApiVersionRegistryFactory;
use Infinity\Evolver\Api\Resolvers\UrlApiVersionResolver;
use Infinity\Evolver\Api\Routing\ApiVersionRouteRegistrar;
use Infinity\Evolver\Commands\ActionMakeCommand;
use Infinity\Evolver\Commands\ApiStatusCommand;
use Infinity\Evolver\Commands\DeployCommand;
use Infinity\Evolver\Commands\StatusCommand;
use Infinity\Evolver\Contracts\ActionIntegrityVerifier;
use Infinity\Evolver\Contracts\ApiVersionResolver;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Database\DatabaseEvolutionRepository;
use Infinity\Evolver\Deploy\Planning\ActionDiscovery;
use Infinity\Evolver\Deploy\Planning\ActionMaterializer;
use Infinity\Evolver\Deploy\Planning\Planner;
use Infinity\Evolver\Deploy\Running\ChecksumActionIntegrityVerifier;
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
            ->hasCommands([
                ActionMakeCommand::class,
                ApiStatusCommand::class,
                DeployCommand::class,
                StatusCommand::class,
            ])
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
        $this->app->singleton(ApiVersionRegistry::class, fn (): ApiVersionRegistry => (new ApiVersionRegistryFactory)
            ->fromArray($this->apiVersions()));

        $this->app->singleton(ApiVersionResolver::class, fn (): ApiVersionResolver => new UrlApiVersionResolver(
            (string) config('evolver.api.base_path', 'api'),
        ));

        $this->app->scoped(ApiVersionContextStore::class);

        $this->app->bind(ApiVersionContext::class, fn ($app): ApiVersionContext => $app
            ->make(ApiVersionContextStore::class)
            ->get());

        $this->app->singleton(ApiVersionRouteRegistrar::class, fn ($app): ApiVersionRouteRegistrar => new ApiVersionRouteRegistrar(
            $app->make(Router::class),
            $app->make(ApiVersionRegistry::class),
            (string) config('evolver.api.base_path', 'api'),
            $this->apiVersions(),
        ));

        $this->app->bind(EvolutionRepository::class, fn (): EvolutionRepository => new DatabaseEvolutionRepository(
            $this->databaseConnection(),
        ));
        $this->app->bind(ActionIntegrityVerifier::class, ChecksumActionIntegrityVerifier::class);
        $this->app->bind(ActionDiscovery::class, fn (): ActionDiscovery => new ActionDiscovery(
            (string) config('evolver.actions_path', base_path('deploy/actions')),
        ));

        $this->app->singleton(VersionManager::class, function (): VersionManager {
            $strategy = $this->versionStrategy();

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
            return new Runner(
                $app->make(EvolutionRepository::class),
                $this->transactionMode(),
                $this->databaseConnection(),
                $app->make(ActionIntegrityVerifier::class),
            );
        });
    }

    /**
     * Register configured API version routes after the package has booted.
     */
    public function packageBooted(): void
    {
        if ((bool) config('evolver.api.enabled', false) && ! $this->app->routesAreCached()) {
            $this->app->make(ApiVersionRouteRegistrar::class)->registerConfigured();
        }
    }

    /**
     * Get the configured version strategy.
     */
    private function versionStrategy(): VersionStrategy
    {
        $configured = config('evolver.versioning.strategy', VersionStrategy::None);
        $strategy = $configured instanceof VersionStrategy
            ? $configured
            : VersionStrategy::tryFrom((string) $configured);

        if ($strategy === null) {
            throw new VersionResolutionException("Unknown version strategy: {$configured}");
        }

        return $strategy;
    }

    /**
     * Get the configured transaction mode.
     */
    private function transactionMode(): TransactionMode
    {
        $configured = config('evolver.transactions.mode', TransactionMode::PerAction);
        $mode = $configured instanceof TransactionMode
            ? $configured
            : TransactionMode::tryFrom((string) $configured);

        if ($mode === null) {
            throw new \InvalidArgumentException("Unknown transaction mode: {$configured}");
        }

        return $mode;
    }

    /**
     * Get the connection shared by evolution persistence and transactions.
     */
    private function databaseConnection(): Connection
    {
        $configured = config('evolver.database.connection');
        $connection = is_string($configured) && $configured !== '' ? $configured : null;

        return $this->app->make(DatabaseManager::class)->connection($connection);
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

    /**
     * Get validated top-level API version configuration.
     *
     * @return array<string, mixed>
     */
    private function apiVersions(): array
    {
        $versions = config('evolver.api.versions', []);

        if (! is_array($versions)) {
            throw new \InvalidArgumentException('The [evolver.api.versions] configuration must be an array.');
        }

        return $versions;
    }
}
