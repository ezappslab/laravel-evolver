<?php

namespace Infinity\Evolver\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\Actionable;

use function Laravel\Prompts\text;

class ReplaceUserModel implements Actionable
{
    /**
     * This method is specifically designed to create a default user in the application's database.
     */
    public function execute(Command $command): void
    {
        $command->info('Replacing the user model...');

        $className = text(
            label: 'What is the user model class name?',
            placeholder: 'E.g. App\\Models\\User',
            required: true,
            hint: 'This will be the class name of the user model when creating the default user',
        );

        $configFile = base_path('config/evolver.php');

        $stub = File::get($configFile);
        $stub = Str::replace('App\\Models\\User::class', $className, $stub);

        File::put($configFile, $stub);

        $command->info('User model replaced');
    }
}
