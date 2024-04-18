<?php

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Illuminate\Filesystem\join_paths;
use function Laravel\Prompts\text;

class MakeEvolverAction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:evolver-action {name? : The name of the action to be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Evolver action class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stub = File::get(__DIR__.'/../../stubs/Action.stub');

        $name = $this->argument('name') ?? text(
            label: 'Action name?',
            placeholder: 'E.g. CreateDefaultUser',
            required: true,
            hint: 'This will be the name of the new action.',
        );

        $stub = Str::replace('{{action}}', $name, $stub);

        File::put(join_paths(app_path(), 'Actions', sprintf('%s.php', $name)), $stub);
    }
}
