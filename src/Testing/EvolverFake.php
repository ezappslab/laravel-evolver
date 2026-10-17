<?php

declare(strict_types=1);

namespace Infinity\Evolver\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Facade;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Contracts\ActionSource;
use Infinity\Evolver\Deployment\Action;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\Planner;
use Infinity\Evolver\Deployment\Runner;
use Infinity\Evolver\EvolverManager;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Exceptions\RepositoryUnavailableException;
use PHPUnit\Framework\Assert;

/**
 * Replaces only the action source; planning, execution, locking, and history stay real.
 */
final readonly class EvolverFake
{
    /**
     * Create a new Evolver fake instance.
     */
    public function __construct(private Container $container, public InMemoryActionSource $source) {}

    /**
     * Swap the container's action source for one serving the given actions.
     *
     * The planner, runner, and managers are singletons holding the previous
     * source, so they are forgotten and rebuilt against the fake on next use.
     *
     * @param  array<string, Action>  $actions
     *
     * @throws InvalidActionException
     */
    public static function install(Container $container, array $actions = []): self
    {
        $source = new InMemoryActionSource($actions);
        $container->instance(ActionSource::class, $source);
        foreach ([Planner::class, Runner::class, DeploymentManager::class, EvolverManager::class] as $class) {
            $container->forgetInstance($class);
        }
        Facade::clearResolvedInstance(EvolverManager::class);

        return new self($container, $source);
    }

    /**
     * Register another action with the fake source.
     *
     *
     * @throws InvalidActionException
     */
    public function add(string $id, Action $action): self
    {
        $this->source->add($id, $action);

        return $this;
    }

    /**
     * Change the action's checksum, so executed actions report as changed.
     *
     *
     * @throws \InvalidArgumentException
     */
    public function touch(string $id): self
    {
        $this->source->touch($id);

        return $this;
    }

    /**
     * Remove the action from discovery, so executed actions report as missing.
     */
    public function forget(string $id): self
    {
        $this->source->forget($id);

        return $this;
    }

    /**
     * Assert that the history table records every given action.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function assertExecuted(string ...$ids): self
    {
        $history = $this->history();
        foreach ($ids as $id) {
            Assert::assertArrayHasKey($id, $history, "Evolver action [{$id}] was not executed.");
        }

        return $this;
    }

    /**
     * Assert that the history table records none of the given actions.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function assertNotExecuted(string ...$ids): self
    {
        $history = $this->history();
        foreach ($ids as $id) {
            Assert::assertArrayNotHasKey($id, $history, "Evolver action [{$id}] was executed.");
        }

        return $this;
    }

    /**
     * Assert that registered actions are absent from the history table.
     *
     * History only keeps active records, so pair this with an earlier
     * assertExecuted() to distinguish a rollback from a skipped action.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function assertRolledBack(string ...$ids): self
    {
        $history = $this->history();
        foreach ($ids as $id) {
            Assert::assertTrue($this->source->has($id), "Evolver action [{$id}] is not registered with the fake.");
            Assert::assertArrayNotHasKey($id, $history, "Evolver action [{$id}] was not rolled back.");
        }

        return $this;
    }

    /**
     * Assert that the history table is empty.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function assertNothingDeployed(): self
    {
        $history = collect($this->history())->keys()->all();
        Assert::assertSame([], $history, 'Evolver actions were deployed: '.Arr::join($history, ', ').'.');

        return $this;
    }

    /**
     * Read the active execution history keyed by action identifier.
     *
     * @return array<string, mixed>
     *
     * @throws RepositoryUnavailableException|BindingResolutionException
     */
    private function history(): array
    {
        return $this->container->make(ActionRepository::class)->all();
    }
}
