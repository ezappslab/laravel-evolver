<?php

it('executes install command', function () {
    $this->artisan('app:install')
        ->expectsQuestion('What is your name?', 'John Doe')
        ->expectsQuestion('What is your email?', 'john.doe@example.com')
        ->expectsQuestion('What is your password?', 'password')
        ->expectsQuestion('What is the version key name?', 'evolver.version')
        ->expectsQuestion('What is the user model class name?', 'App\\Models\\User::class')
        ->assertSuccessful()
        ->expectsOutputToContain('installed');
});
