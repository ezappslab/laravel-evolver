<?php

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Infinity\Evolver\Commands\Concerns\DisplaysActionList;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Deploy\Planning\ActionPlanBuilder;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Version\VersionManager;

class StatusCommand extends Command
{
    use DisplaysActionList;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evolver:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the current status of data evolutions';

    /**
     * Execute the console command.
     */
    public function handle(
        VersionManager $versionManager,
        ActionPlanBuilder $planBuilder,
        ActionRepository $repository
    ): int {
        $currentVersion = $versionManager->current();
        $currentValue = $currentVersion?->value() ?? 'None';
        $this->components->info("Current Version: <comment>{$currentValue}</comment>");

        try {
            $targetVersion = $versionManager->targetRequired();
            $targetValue = $targetVersion?->value() ?? 'None';
            $this->components->info("Target Version: <comment>{$targetValue}</comment>");
        } catch (\Exception $e) {
            $this->components->info('Target Version: <comment>None</comment>');
        }

        $this->newLine();

        $plan = $planBuilder->build();

        $toRun = collect($plan->toRun)->map(fn ($d) => [
            'id' => $d->actionId,
            'status' => ActionStatus::Pending,
            'duration' => null,
            'batch_id' => null,
        ]);

        $runs = $this->latestRunsByAction($repository);

        $skipped = collect($plan->skipped)->map(fn ($s) => [
            'id' => $s['descriptor']->actionId,
            'status' => $s['status'],
            'duration' => $runs[$s['descriptor']->actionId]['duration_ms'] ?? null,
            'batch_id' => $runs[$s['descriptor']->actionId]['batch_id'] ?? null,
        ])->all();

        $all = $toRun->concat($skipped)->sortBy('id');

        if ($all->isEmpty()) {
            $this->comment('No actions found.');

            return Command::SUCCESS;
        }

        $this->displayActions($all);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function latestRunsByAction(ActionRepository $repository): array
    {
        return collect($repository->listRuns())
            ->unique('action_id')
            ->keyBy('action_id')
            ->all();
    }
}
