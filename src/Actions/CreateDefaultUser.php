<?php

namespace Infinity\Evolver\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Infinity\Evolver\Contracts\Actionable;

use function Laravel\Prompts\form;

class CreateDefaultUser implements Actionable
{
    /**
     * This method is specifically designed to create a default
     * user in the application's database.
     */
    public function execute(Command $command): void
    {
        $command->info('Creating default user...');

        $userModel = config('evolver.user_model');

        $responses = form()
            ->text('What is your name?', required: true, name: 'name')
            ->text('What is your email?', required: true, name: 'email')
            ->password(
                'What is your password?',
                validate: ['password' => 'min:8'],
                name: 'password',
            )
            ->submit();

        $user = new $userModel([
            'name' => $responses['name'],
            'email' => $responses['email'],
            'password' => Hash::make($responses['password']),
        ]);

        $user->save();

        $command->info('Default user created');
    }
}
