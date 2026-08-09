<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Infinity\Evolver\Commands\Concerns\DisplaysActionList;
use Infinity\Evolver\Deploy\Planning\Planner;
use Infinity\Evolver\Version\VersionManager;

final class StatusCommand extends Command
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
    protected $description = 'Display the status of evolution actions';

    /**
     * Execute the console command.
     */
    public function handle(Planner $planner, VersionManager $versions): int
    {
        $plan = $planner->plan();
        $target = $plan->targetVersion?->value() ?? 'None';
        $this->components->info("Version strategy: {$versions->strategy()->value}; target: {$target}");
        $this->displayPlan($plan);

        return self::SUCCESS;
    }
}
