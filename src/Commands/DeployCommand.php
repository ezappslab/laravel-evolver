<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\ExecutionPlan;

/** Plans and executes pending actions from the command line. */
final class DeployCommand extends Command
{
    use ConfirmableTrait;

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
        if ($this->laravel->environment('production') && ! $this->option('force') && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            $result = $manager->deploy((bool) $this->option('pretend'));
            $ids = $result instanceof ExecutionPlan ? array_map(static fn ($a): string => $a->descriptor->id, $result->actions) : $result->completed;
            foreach ($ids as $id) {
                $this->line(($result instanceof ExecutionPlan ? 'Would execute: ' : 'Executed: ').$id);
            }

            $this->components->info($ids === [] ? 'Nothing to deploy.' : 'Deployment complete.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
