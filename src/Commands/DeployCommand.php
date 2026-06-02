<?php

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Infinity\Evolver\Commands\Concerns\DisplaysActionList;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Deploy\Deployer;
use Infinity\Evolver\Deploy\DeployerResult;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Planning\ActionStatus;

class DeployCommand extends Command
{
    use ConfirmableTrait;
    use DisplaysActionList;

    protected ActionRepository $repository;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evolver:deploy 
                            {--dry-run : Display the actions that would be executed without actually running them}
                            {--force : Force the operation to run when in production}
                            {--allow-changed : Allow actions that have changed since their last successful run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the pending data evolutions';

    /**
     * Execute the console command.
     */
    public function handle(Deployer $deployer, ActionRepository $repository): int
    {
        $this->repository = $repository;

        if (! $this->confirmToProceed()) {
            return Command::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->displayPlan($deployer->plan());

            return Command::SUCCESS;
        }

        $this->components->info('Starting data evolutions...');

        try {
            $result = $deployer->deploy($this->option('allow-changed'));

            if ($result === null) {
                $this->components->warn('Deployment skipped: target version could not be resolved and is not required.');

                return Command::SUCCESS;
            }

            $this->displayResult($result);
        } catch (\Exception $e) {
            $this->components->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->newLine();

        return Command::SUCCESS;
    }

    /**
     * Display the action plan.
     */
    protected function displayPlan(ActionPlan $plan): void
    {
        $this->components->info('Evolver Dry Run - Action Plan:');

        $toRun = collect($plan->toRun)->map(fn ($d) => [
            'id' => $d->actionId,
            'status' => ActionStatus::Pending,
            'duration' => null,
            'batch_id' => null,
        ]);

        $runs = $this->latestRunsByAction();

        $skipped = collect($plan->skipped)->map(fn ($s) => [
            'id' => $s['descriptor']->actionId,
            'status' => $s['status'],
            'duration' => $runs[$s['descriptor']->actionId]['duration_ms'] ?? null,
            'batch_id' => $runs[$s['descriptor']->actionId]['batch_id'] ?? null,
        ])->all();

        $all = $toRun->concat($skipped)->sortBy('id');

        if ($all->isEmpty()) {
            $this->comment('No actions found.');

            return;
        }

        $this->displayActions($all->all());
    }

    /**
     * Display the deployment result.
     */
    protected function displayResult(DeployerResult $result): void
    {
        $this->components->info('Deployment completed successfully.');

        $toRun = collect($result->plannedToRun)->map(fn ($d) => [
            'id' => $d->actionId,
            'status' => ActionStatus::Success,
            'duration' => null,
            'batch_id' => $result->batchId,
        ]);

        $runs = $this->latestRunsByAction($result->batchId);

        $toRun = $toRun->map(fn ($item) => [
            ...$item,
            'duration' => $runs[$item['id']]['duration_ms'] ?? null,
            'batch_id' => $runs[$item['id']]['batch_id'] ?? $item['batch_id'],
        ]);

        $allRuns = $this->latestRunsByAction();

        $skipped = collect($result->skipped)->map(fn ($s) => [
            'id' => $s['descriptor']->actionId,
            'status' => $s['status'],
            'duration' => $allRuns[$s['descriptor']->actionId]['duration_ms'] ?? null,
            'batch_id' => $allRuns[$s['descriptor']->actionId]['batch_id'] ?? null,
        ])->all();

        $all = $toRun->concat($skipped)->sortBy('id');

        $this->displayActions($all->all());

        $this->components->info("Batch ID: <comment>{$result->batchId}</comment>");
        $targetValue = $result->targetVersion->value() ?? 'None';
        $this->components->info("Target Version: <comment>{$targetValue}</comment>");
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function latestRunsByAction(?string $batchId = null): array
    {
        return collect($this->repository->listRuns())
            ->when($batchId !== null, fn ($runs) => $runs->where('batch_id', $batchId))
            ->unique('action_id')
            ->keyBy('action_id')
            ->all();
    }
}
