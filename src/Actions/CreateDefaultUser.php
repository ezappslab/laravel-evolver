<?php

namespace Infinity\Evolver\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Infinity\Evolver\Contracts\Actionable;

class CreateDefaultUser implements Actionable
{
    protected string $userModelClass = 'App\\Models\\User';

    public function execute(Command $command): void
    {
        $command->info('Creating default user...');

        $user = new $this->userModelClass([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password')
        ]);

        $user->save();

        $command->info('Default user created');
    }
}