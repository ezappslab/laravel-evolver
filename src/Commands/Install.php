<?php

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;

class Install extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Installing the application...');

        $actions = config('config.evolver.install', []);
        foreach ($actions as $action) {
            app($action)->execute($this);
        }

        $this->info('Application installed.');
    }
}
