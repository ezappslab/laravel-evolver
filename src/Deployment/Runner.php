<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

use Illuminate\Database\Connection;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\ActionExecutionException;

/** Executes a supplied plan and keeps history writes in its transaction boundary. */
final class Runner
{
    /**
     * Create a new runner instance.
     */
    public function __construct(private readonly ActionRepository $repository, private readonly Connection $connection, private readonly TransactionMode $mode) {}

    /**
     * Execute the plan according to its direction and configured transaction mode.
     *
     *
     * @throws ActionExecutionException
     */
    public function run(ExecutionPlan $plan): ExecutionResult
    {
        if ($plan->isEmpty()) {
            return new ExecutionResult($plan->direction, $plan->batch, []);
        }

        return $this->mode === TransactionMode::Batch
            ? $this->batch($plan)
            : $this->incremental($plan);
    }

    /**
     * Execute actions independently, optionally wrapping each action in a transaction.
     *
     *
     * @throws ActionExecutionException
     */
    private function incremental(ExecutionPlan $plan): ExecutionResult
    {
        $done = [];
        foreach ($plan->actions as $action) {
            try {
                if ($this->mode === TransactionMode::Action) {
                    $this->connection->transaction(fn () => $this->execute($action, $plan));
                } else {
                    $this->execute($action, $plan);
                }

                $done[] = $action->descriptor->id;
            } catch (\Throwable $e) {
                throw new ActionExecutionException($action->descriptor->id, $done, $e);
            }
        }

        return new ExecutionResult($plan->direction, $plan->batch, $done);
    }

    /**
     * Execute the complete plan and its history mutations in one transaction.
     *
     *
     * @throws ActionExecutionException
     */
    private function batch(ExecutionPlan $plan): ExecutionResult
    {
        $current = $plan->actions[0]->descriptor->id;
        try {
            $done = $this->connection->transaction(function () use ($plan, &$current): array {
                $done = [];
                foreach ($plan->actions as $action) {
                    $current = $action->descriptor->id;
                    $this->execute($action, $plan);
                    $done[] = $current;
                }

                return $done;
            });
        } catch (\Throwable $e) {
            throw new ActionExecutionException($current, [], $e);
        }

        return new ExecutionResult($plan->direction, $plan->batch, $done);
    }

    /**
     * Execute a single planned action and update the execution history.
     *
     * The action source is revalidated immediately before invoking the action
     * and mutating history.
     *
     *
     * @throws ActionChangedException
     */
    private function execute(PlannedAction $action, ExecutionPlan $plan): void
    {
        $checksum = hash_file('sha256', $action->descriptor->path);
        if (! hash_equals($action->descriptor->checksum, $checksum)) {
            throw new ActionChangedException("Action [{$action->descriptor->id}] changed after planning.");
        }

        $start = hrtime(true);
        if ($plan->direction === Direction::Up) {
            $action->descriptor->action->up();
            $this->repository->record($action->descriptor->id, $checksum, $plan->batch, (int) ((hrtime(true) - $start) / 1_000_000), $plan->applicationVersion);
        } else {
            $action->descriptor->action->down();
            $this->repository->remove($action->descriptor->id);
        }
    }
}
