<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Infinity\Evolver\Commands\Concerns\RendersActions;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\ExecutionPlan;

/** Plans and executes pending actions from the command line. */
final class DeployCommand extends Command
{
    use ConfirmableTrait;
    use RendersActions;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evolver:deploy {--pretend : Plan without execution} {--force : Skip production confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute pending Evolver actions';

    /**
     * Execute the console command.
     *
     * Production runs require confirmation unless --force is given, and --pretend
     * lists the pending actions without executing them.
     */
    public function handle(DeploymentManager $manager): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            $pretend = (bool) $this->option('pretend');
            $this->components->info($pretend ? 'Planning Evolver deployment.' : 'Deploying Evolver actions.');
            $result = $manager->deploy($pretend);
            $pretending = $result instanceof ExecutionPlan;
            $actions = $pretending ? $result->actions : $result->completedActions();
            foreach ($actions as $action) {
                $this->renderAction($action->descriptor->id, $action->descriptor->loaded(), $pretending ? 'would execute' : 'executed', $pretending ? 'yellow' : 'green', $result->batch);
            }

            $this->components->info($actions === [] ? 'Nothing to deploy.' : 'Deployment complete.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
