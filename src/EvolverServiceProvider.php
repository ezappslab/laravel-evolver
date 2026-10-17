<?php

declare(strict_types=1);

namespace Infinity\Evolver;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Routing\Router;
use Infinity\Evolver\Commands\ActionCommand;
use Infinity\Evolver\Commands\DeployCommand;
use Infinity\Evolver\Commands\RollbackCommand;
use Infinity\Evolver\Commands\StatusCommand;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Contracts\ActionSource;
use Infinity\Evolver\Database\DatabaseActionRepository;
use Infinity\Evolver\Deployment\ActionDiscovery;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\Planner;
use Infinity\Evolver\Deployment\Runner;
use Infinity\Evolver\Deployment\TransactionMode;
use Infinity\Evolver\Routing\ApiVersionContext;
use Infinity\Evolver\Routing\ResolveApiVersion;
use Infinity\Evolver\Versioning\VersionManager;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/** Registers Evolver's publishable assets, commands, services, and routing hooks. */
final class EvolverServiceProvider extends PackageServiceProvider
{
    private const string DATABASE_CONNECTION = 'evolver.database.connection';

    /**
     * Configure the package's config, migrations, commands, and installer.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-evolver')
            ->hasConfigFile('evolver')
            ->hasMigration('create_evolver_tables')
            ->runsMigrations()
            ->hasCommands(ActionCommand::class, StatusCommand::class, DeployCommand::class, RollbackCommand::class)
            ->hasInstallCommand(fn (InstallCommand $command) => $command
                ->publishConfigFile()
                ->publishMigrations()
                ->askToRunMigrations());
    }

    /**
     * Register the package services in the container.
     *
     * Runs after the package configuration has been merged.
     */
    public function packageRegistered(): void
    {
        $this->app->scoped(ApiVersionContext::class);
        $this->app->singleton(VersionManager::class);
        $this->app->singleton(self::DATABASE_CONNECTION, fn ($app): Connection => $app['db']->connection(config('evolver.database.connection')));
        $this->app->singleton(LockProvider::class, function ($app): LockProvider {
            $store = $app['cache.store']->getStore();
            if (! $store instanceof LockProvider) {
                throw new \LogicException('The cache store must support atomic locks.');
            }

            return $store;
        });
        $this->app->singleton(ActionRepository::class, fn ($app) => new DatabaseActionRepository($app->make(self::DATABASE_CONNECTION)));
        $this->app->singleton(ActionDiscovery::class, fn ($app) => new ActionDiscovery($app['files'], (string) config('evolver.actions.path')));
        $this->app->singleton(ActionSource::class, ActionDiscovery::class);
        $this->app->singleton(Planner::class, fn ($app) => new Planner($app->make(ActionSource::class), $app->make(ActionRepository::class), $app->make(VersionManager::class), (bool) config('evolver.safety.fail_on_changed_action', true)));
        $this->app->singleton(Runner::class, fn ($app) => new Runner($app->make(ActionRepository::class), $app->make(ActionSource::class), $app->make(self::DATABASE_CONNECTION), TransactionMode::configured(config('evolver.transactions.mode', 'action'))));
        $this->app->singleton(DeploymentManager::class, fn ($app) => new DeploymentManager($app->make(Planner::class), $app->make(Runner::class), $app->make(LockProvider::class), (int) config('evolver.lock.seconds', 300)));
        $this->app->singleton(EvolverManager::class);
    }

    /**
     * Bootstrap the package's routing integration.
     *
     * Registers the Route::apiVersion() macro and prepends the API version
     * middleware so versions are resolved before route matching.
     */
    public function packageBooted(): void
    {
        Router::macro('apiVersion', function (string $version, \Closure|string $routes, array $middleware = ['api']): Router {
            /** @var Router $this */
            $group = $this->middleware($middleware)
                ->prefix(app(VersionManager::class)->apiBasePath().'/'.$version);

            $group->group($routes);

            return $this;
        });

        if ($this->app->bound(Kernel::class)) {
            $this->app->make(Kernel::class)->prependMiddleware(ResolveApiVersion::class);
        }
    }
}
