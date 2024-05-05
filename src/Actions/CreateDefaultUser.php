<?php

namespace Infinity\Evolver\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Infinity\Evolver\Contracts\Actionable;

class CreateDefaultUser implements Actionable
{
    /**
     * This method is specifically designed to create a default user in the application's database.
     */
    public function execute(Command $command): void
    {
        $command->info('Creating default user...');

        $userModel = config('evolver.user_model');

        $user = new $userModel([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
        ]);

        $user->save();

        $command->info('Default user created');
    }
}
