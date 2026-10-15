<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Exceptions\LockException;
use Infinity\Evolver\Exceptions\VersionResolutionException;

/** Coordinates final planning and execution under one atomic lock. */
final class DeploymentManager
{
    /**
     * Create a new deployment manager instance.
     */
    public function __construct(private readonly Planner $planner, private readonly Runner $runner, private readonly LockProvider $locks, private readonly int $lockSeconds = 300) {}

    /**
     * Inspect all action states without mutating execution history.
     *
     *
     * @throws InvalidActionException
     * @throws VersionResolutionException
     */
    public function status(): DeploymentStatus
    {
        return $this->planner->status();
    }

    /**
     * Build a forward plan without acquiring the execution lock.
     *
     *
     * @throws ActionChangedException
     * @throws InvalidActionException
     * @throws VersionResolutionException
     */
    public function plan(): ExecutionPlan
    {
        return $this->planner->deployment();
    }

    /**
     * Build a rollback plan for the supplied batch or the latest batch.
     *
     *
     * @throws ActionChangedException
     * @throws InvalidActionException
     */
    public function rollbackPlan(?string $batch = null): ExecutionPlan
    {
        return $this->planner->rollback($batch);
    }

    /**
     * Plan and optionally execute pending actions under one atomic lock.
     *
     * Pretend mode returns the exact plan while skipping actions and persistence.
     * Planning and execution exceptions from the planner and runner propagate.
     *
     *
     * @throws LockException
     */
    public function deploy(bool $pretend = false): ExecutionPlan|ExecutionResult
    {
        return $this->locked(function () use ($pretend) {
            $plan = $this->planner->deployment();

            return $pretend ? $plan : $this->runner->run($plan);
        });
    }

    /**
     * Plan and optionally reverse a batch under the deployment lock.
     *
     * Planning and execution exceptions from the planner and runner propagate.
     *
     *
     * @throws LockException
     */
    public function rollback(?string $batch = null, bool $pretend = false): ExecutionPlan|ExecutionResult
    {
        return $this->locked(function () use ($batch, $pretend) {
            $plan = $this->planner->rollback($batch);

            return $pretend ? $plan : $this->runner->run($plan);
        });
    }

    /**
     * Run the callback while holding the deployment lock.
     *
     * Keeps final planning and execution in the same critical section.
     *
     *
     * @throws LockException
     */
    private function locked(callable $callback): mixed
    {
        $lock = $this->locks->lock('evolver:deployment', $this->lockSeconds);
        $acquired = false;

        try {
            $acquired = (bool) $lock->get();
            if (! $acquired) {
                throw new LockException('Another Evolver operation is already running.');
            }

            return $callback();
        } catch (LockTimeoutException $e) {
            throw new LockException('Timed out waiting for the Evolver deployment lock.', 0, $e);
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }
    }
}
