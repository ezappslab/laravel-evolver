<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Running;

use Illuminate\Support\Facades\DB;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Planning\DeploymentPlan;
use Infinity\Evolver\Exceptions\ActionFailedException;
use Throwable;

final class Runner
{
    /**
     * Create an action runner for the configured transaction boundary.
     */
    public function __construct(
        private readonly EvolutionRepository $repository,
        private readonly TransactionMode $transactionMode,
    ) {}

    /**
     * Execute the pending actions in their planned order.
     */
    public function run(DeploymentPlan $plan, string $batchId): ExecutionResult
    {
        return match ($this->transactionMode) {
            TransactionMode::None => $this->runIncrementally($plan, $batchId, false),
            TransactionMode::PerAction => $this->runIncrementally($plan, $batchId, true),
            TransactionMode::EntireRun => $this->runEntirely($plan, $batchId),
        };
    }

    /**
     * Execute actions without a run-wide transaction.
     */
    private function runIncrementally(DeploymentPlan $plan, string $batchId, bool $transactional): ExecutionResult
    {
        $committed = [];

        foreach ($plan->pending() as $action) {
            try {
                if ($transactional) {
                    DB::transaction(fn () => $this->execute($action, $batchId, $plan));
                } else {
                    $this->execute($action, $batchId, $plan);
                }

                $committed[] = $action->descriptor->actionId;
            } catch (Throwable $exception) {
                throw $this->failure($action, $batchId, $committed, $exception);
            }
        }

        return new ExecutionResult($batchId, $committed);
    }

    /**
     * Execute every pending action in one transaction.
     */
    private function runEntirely(DeploymentPlan $plan, string $batchId): ExecutionResult
    {
        $current = null;

        try {
            $committed = DB::transaction(function () use ($plan, $batchId, &$current): array {
                $executed = [];

                foreach ($plan->pending() as $action) {
                    $current = $action;
                    $this->execute($action, $batchId, $plan);
                    $executed[] = $action->descriptor->actionId;
                }

                return $executed;
            });
        } catch (Throwable $exception) {
            if ($current instanceof ActionPlan) {
                throw $this->failure($current, $batchId, [], $exception);
            }

            throw $exception;
        }

        return new ExecutionResult($batchId, $committed);
    }

    /**
     * Invoke an action and record its successful return.
     */
    private function execute(ActionPlan $action, string $batchId, DeploymentPlan $plan): void
    {
        $startedAt = hrtime(true);
        $action->action->handle();
        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        $this->repository->record(
            $batchId,
            $action->descriptor->actionId,
            $action->descriptor->checksum,
            $plan->targetVersion?->value(),
            $durationMs,
        );
    }

    /**
     * Add action context and committed execution state to a failure.
     *
     * @param  list<string>  $committed
     */
    private function failure(
        ActionPlan $action,
        string $batchId,
        array $committed,
        Throwable $exception,
    ): ActionFailedException {
        if ($exception instanceof ActionFailedException) {
            return $exception;
        }

        return new ActionFailedException(
            $action->descriptor->actionId,
            $action->descriptor->path,
            new ExecutionResult($batchId, $committed),
            $exception,
        );
    }
}
