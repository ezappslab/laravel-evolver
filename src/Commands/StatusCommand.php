<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Infinity\Evolver\Deployment\ActionDescriptor;
use Infinity\Evolver\Deployment\DeploymentManager;

/** Displays application version, action integrity, and batch information. */
final class StatusCommand extends Command
{
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
    protected $description = 'Show Evolver deployment status';

    /**
     * Execute the console command.
     *
     * Renders the application version and a table of discovered and historical
     * actions with their pending, executed, changed, missing, or not applicable state.
     */
    public function handle(DeploymentManager $manager): int
    {
        $status = $manager->status();
        $this->components->info('Application version: '.($status->applicationVersion ?? 'none'));
        $pending = array_fill_keys(array_map(static fn (ActionDescriptor $a): string => $a->id, $status->pending), true);
        $changed = array_fill_keys(array_map(static fn (ActionDescriptor $a): string => $a->id, $status->changed), true);
        $rows = [];
        foreach ($status->discovered as $action) {
            $record = $status->executed[$action->id] ?? null;
            $state = isset($changed[$action->id]) ? 'changed' : (isset($pending[$action->id]) ? 'pending' : ($record ? 'executed' : 'not applicable'));
            $rows[] = [$action->id, $state, $record === null ? '—' : $record->batch];
        }

        foreach ($status->missing as $record) {
            $rows[] = [$record->id, 'missing', $record->batch];
        }

        $this->table(['Action', 'Status', 'Batch'], $rows);

        return self::SUCCESS;
    }
}
