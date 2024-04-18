<?php

it('executes Install command', function () {
    $this->artisan('app:install')
        ->assertSuccessful()
        ->expectsOutput('Installed');
});
