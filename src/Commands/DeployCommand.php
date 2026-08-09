<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Infinity\Evolver\Commands\Concerns\DisplaysActionList;
use Infinity\Evolver\Deploy\Deployer;
use Infinity\Evolver\Exceptions\ActionFailedException;
use Throwable;

final class DeployCommand extends Command
{
    use ConfirmableTrait;
    use DisplaysActionList;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evolver:deploy
        {--dry-run : Display the exact deployment plan without executing it}
        {--force : Force the operation to run in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy pending evolution actions';

    /**
     * Execute the console command.
     */
    public function handle(Deployer $deployer): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if ((bool) $this->option('dry-run')) {
            $this->components->info('Dry run: no actions will be executed.');
            $this->displayPlan($deployer->plan());

            return self::SUCCESS;
        }

        try {
            $result = $deployer->deploy();
        } catch (ActionFailedException $exception) {
            $this->components->error($exception->getMessage());
            $this->components->warn('Committed before failure: '.count($exception->result->committedActionIds));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->displayPlan($result->plan);
        $this->components->info('Committed '.count($result->execution->committedActionIds).' action(s).');
        $this->components->info("Batch ID: {$result->execution->batchId}");

        return self::SUCCESS;
    }
}
