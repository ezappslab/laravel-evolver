<?php

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ActionMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evolver:action {name : The name of the action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new evolver action';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $name = str($this->argument('name'))->snake();
        $timestamp = now()->format('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";

        $path = config('evolver.actions_path', base_path('deploy/actions'));

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $fullPath = $path.DIRECTORY_SEPARATOR.$filename;

        if (File::exists($fullPath)) {
            $this->error('Action already exists!');

            return;
        }

        File::put($fullPath, $this->getStub());

        $this->info("Action created successfully: {$filename}");
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        $stubPath = __DIR__.'/../../resources/stubs/action.stub';

        if (! File::exists($stubPath)) {
            throw new RuntimeException("Action stub not found: {$stubPath}");
        }

        return File::get($stubPath);
    }
}
