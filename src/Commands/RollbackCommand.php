<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Infinity\Evolver\Commands\Concerns\RendersActions;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\ExecutionPlan;

/** Plans and reverses the latest or explicitly selected deployment batch. */
final class RollbackCommand extends Command
{
    use ConfirmableTrait;
    use RendersActions;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evolver:rollback {--batch= : Batch UUID} {--pretend : Plan without execution} {--force : Skip production confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Roll back an Evolver batch';

    /**
     * Execute the console command.
     *
     * Rolls back the latest batch, or the batch given by --batch. Production runs
     * require confirmation unless --force is given, and --pretend lists the
     * actions that would be rolled back without executing them.
     */
    public function handle(DeploymentManager $manager): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            $batch = $this->option('batch');
            $batch = is_string($batch) && $batch !== '' ? $batch : null;
            $pretend = (bool) $this->option('pretend');
            $this->components->info(($pretend ? 'Planning rollback of ' : 'Rolling back ').($batch === null ? 'the latest Evolver batch.' : "Evolver batch [{$batch}]."));
            $result = $manager->rollback($batch, $pretend);
            $pretending = $result instanceof ExecutionPlan;
            $actions = $pretending ? $result->actions : $result->completedActions();
            foreach ($actions as $action) {
                $this->renderAction($action->descriptor->id, $action->descriptor->loaded(), $pretending ? 'would roll back' : 'rolled back', $pretending ? 'yellow' : 'green', $result->batch);
            }

            $this->components->info($actions === [] ? 'Nothing to roll back.' : 'Rollback complete.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
