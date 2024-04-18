<?php

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;

class Upgrade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upgrade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Applying necessary compatibility adjustments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Installing the application...');

        $actions = config('config.evolver.upgrade', []);
        foreach ($actions as $action) {
            app($action)->execute($this);
        }

        $this->info('Application upgraded.');
    }
}
