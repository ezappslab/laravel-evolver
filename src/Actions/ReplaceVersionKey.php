<?php

namespace Infinity\Evolver\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\Actionable;

use function Laravel\Prompts\text;

class ReplaceVersionKey implements Actionable
{
    /**
     * This method is specifically designed to create a default user in the application's database.
     */
    public function execute(Command $command): void
    {
        $command->info('Replacing the version key...');

        $versionKey = text(
            label: 'What is the version key name?',
            placeholder: 'E.g. app.version',
            required: true,
            hint: 'This will be the versionKey of the version key when detecting the version from the configuration.',
        );

        $configFile = base_path('config/evolver.php');

        $stub = File::get($configFile);
        $stub = Str::replace('evolver.version', $versionKey, $stub);

        File::put($configFile, $stub);

        $command->info('Version key replaced');
    }
}
