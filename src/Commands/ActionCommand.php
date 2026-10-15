<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/** Generates timestamped deployment actions from the package stub. */
final class ActionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evolver:action {name : Action name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a timestamped Evolver action';

    /**
     * Execute the console command.
     *
     * Creates the configured actions directory when needed and writes a new
     * timestamped action file from the package stub. Names are restricted to
     * a safe character set so they cannot escape the actions directory.
     */
    public function handle(Filesystem $files): int
    {
        $name = (string) $this->argument('name');
        if (! Str::isMatch('/^[A-Za-z0-9][A-Za-z0-9 _-]*$/', $name)) {
            $this->components->error('The action name may only contain letters, numbers, spaces, underscores, and hyphens.');

            return self::FAILURE;
        }

        $path = (string) config('evolver.actions.path');
        $files->ensureDirectoryExists($path);
        $file = $path.'/'.now()->format('Y_m_d_His').'_'.Str::snake($name).'.php';
        if ($files->exists($file)) {
            $this->components->error('An action with this timestamp and name already exists.');

            return self::FAILURE;
        }

        $files->put($file, $files->get(dirname(__DIR__, 2).'/resources/stubs/action.stub'));
        $this->components->info("Created action: {$file}");

        return self::SUCCESS;
    }
}
