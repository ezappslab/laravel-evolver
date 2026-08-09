<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class ActionMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evolver:action {name : The action class name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new evolution action';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = (string) $this->argument('name');
        $class = str($input)->studly()->value();

        if ($class === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $class)) {
            $this->components->error('The action name must produce a valid PHP class name.');

            return self::FAILURE;
        }

        $directory = (string) config('evolver.actions_path', base_path('deploy/actions'));
        File::ensureDirectoryExists($directory);
        $filename = now()->format('Y_m_d_His').'_'.str($class)->snake().'.php';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (File::exists($path)) {
            $this->components->error("Action already exists: {$filename}");

            return self::FAILURE;
        }

        File::put($path, $this->stub());
        $this->components->info("Action created: {$filename}");

        return self::SUCCESS;
    }

    /**
     * Get the contents of the action stub.
     */
    private function stub(): string
    {
        $path = dirname(__DIR__, 2).'/resources/stubs/action.stub';

        if (! File::exists($path)) {
            throw new RuntimeException("Action stub not found: {$path}");
        }

        return File::get($path);
    }
}
