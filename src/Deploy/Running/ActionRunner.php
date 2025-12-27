<?php

namespace Infinity\Evolver\Deploy\Running;

use Illuminate\Support\Facades\DB;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Contracts\Version;
use Infinity\Evolver\Deploy\Planning\ActionDescriptor;
use Infinity\Evolver\Deploy\Planning\ActionMaterializer;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\ActionFailedException;
use Throwable;

/**
 * Runs the actions in an ActionPlan.
 */
class ActionRunner
{
    /**
     * Create a new action runner instance.
     */
    public function __construct(
        protected ActionRepository $repository,
        protected ActionMaterializer $materializer,
        protected TransactionMode $transactionMode = TransactionMode::PerAction
    ) {}

    /**
     * Create a new action runner instance from configuration.
     */
    public static function fromConfig(ActionRepository $repository, ActionMaterializer $materializer): self
    {
        $mode = config('evolver.transactions.mode', TransactionMode::PerAction);

        if (! $mode instanceof TransactionMode) {
            $mode = TransactionMode::tryFrom($mode) ?? TransactionMode::PerAction;
        }

        return new self($repository, $materializer, $mode);
    }

    /**
     * Run the given action plan.
     *
     * @throws ActionFailedException
     * @throws ActionChangedException|Throwable
     */
    public function run(ActionPlan $plan, string $batchId, Version $targetVersion, bool $allowChanged = false): void
    {
        if ($this->transactionMode === TransactionMode::All) {
            DB::transaction(fn () => $this->runActions($plan, $batchId, $targetVersion, $allowChanged));
        } else {
            $this->runActions($plan, $batchId, $targetVersion, $allowChanged);
        }
    }

    /**
     * Execute the actions in the plan.
     */
    protected function runActions(ActionPlan $plan, string $batchId, Version $targetVersion, bool $allowChanged): void
    {
        foreach ($plan->toRun as $descriptor) {
            if ($this->transactionMode === TransactionMode::PerAction) {
                DB::transaction(fn () => $this->runAction($descriptor, $batchId, $targetVersion, $allowChanged));
            } else {
                $this->runAction($descriptor, $batchId, $targetVersion, $allowChanged);
            }
        }
    }

    /**
     * Run a single action.
     *
     * @throws ActionFailedException
     * @throws ActionChangedException
     */
    protected function runAction(ActionDescriptor $descriptor, string $batchId, Version $targetVersion, bool $allowChanged): void
    {
        $previousChecksum = $this->repository->getSuccessfulRunChecksum($descriptor->actionId);

        if (! $allowChanged && $previousChecksum && $previousChecksum !== $descriptor->checksum) {
            throw new ActionChangedException(
                $descriptor->actionId,
                $descriptor->path,
                $previousChecksum,
                $descriptor->checksum
            );
        }

        $start = now();
        $action = $this->materializer->materialize($descriptor);
        $metadata = $this->materializer->getMetadata($action);

        try {
            $action->handle();

            $duration = (int) now()->diffInMilliseconds($start);

            $this->repository->recordSuccess(
                $batchId,
                $descriptor->actionId,
                $descriptor->checksum,
                $metadata['introducedIn'],
                $metadata['requiredUntil'],
                $targetVersion->value() ?? 'unknown',
                $duration
            );
        } catch (Throwable $e) {
            $duration = (int) now()->diffInMilliseconds($start);

            $this->repository->recordFailure(
                $batchId,
                $descriptor->actionId,
                $descriptor->checksum,
                $metadata['introducedIn'],
                $metadata['requiredUntil'],
                $targetVersion->value() ?? 'unknown',
                $duration,
                $e
            );

            throw new ActionFailedException($descriptor->actionId, $descriptor->path, $e);
        }
    }
}
