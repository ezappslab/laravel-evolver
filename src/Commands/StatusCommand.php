<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Infinity\Evolver\Commands\Concerns\RendersActions;
use Infinity\Evolver\Deployment\ActionDescriptor;
use Infinity\Evolver\Deployment\ActionState;
use Infinity\Evolver\Deployment\DeploymentManager;

/** Displays application version, action integrity, and batch information. */
final class StatusCommand extends Command
{
    use RendersActions;

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
     * Renders the application version and one line per discovered and historical
     * action with its pending, executed, changed, missing, or not applicable state.
     * Actions loaded during planning show their version window, and executed
     * actions show their batch on the line below.
     */
    public function handle(DeploymentManager $manager): int
    {
        $status = $manager->status();
        $this->components->info('Evolver deployment status.');
        $this->components->twoColumnDetail('Application version', $status->applicationVersion ?? '<fg=gray>none</>');
        $this->newLine();
        foreach ([...$status->discovered, ...$status->missing] as $action) {
            $record = $status->executed[$action->id] ?? null;
            $state = $status->stateOf($action->id);
            $color = match ($state) {
                ActionState::Executed => 'green',
                ActionState::Pending => 'yellow',
                ActionState::Changed, ActionState::Missing => 'red',
                default => 'gray',
            };

            $this->renderAction($action->id, $action instanceof ActionDescriptor ? $action->loaded() : null, (string) $state?->value, $color, $record?->batch);
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
