<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\ExecutionPlan;

/** Plans and reverses the latest or explicitly selected deployment batch. */
final class RollbackCommand extends Command
{
    use ConfirmableTrait;

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
            $result = $manager->rollback(is_string($batch) && $batch !== '' ? $batch : null, (bool) $this->option('pretend'));
            $ids = $result instanceof ExecutionPlan ? array_map(static fn ($a): string => $a->descriptor->id, $result->actions) : $result->completed;
            foreach ($ids as $id) {
                $this->line(($result instanceof ExecutionPlan ? 'Would roll back: ' : 'Rolled back: ').$id);
            }

            $this->components->info($ids === [] ? 'Nothing to roll back.' : 'Rollback complete.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
