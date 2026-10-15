<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
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
        $rows = [];
        foreach ([...$status->discovered, ...$status->missing] as $action) {
            $record = $status->executed[$action->id] ?? null;
            $rows[] = [$action->id, $status->stateOf($action->id)?->value, $record === null ? '—' : $record->batch];
        }

        $this->table(['Action', 'Status', 'Batch'], $rows);

        return self::SUCCESS;
    }
}
