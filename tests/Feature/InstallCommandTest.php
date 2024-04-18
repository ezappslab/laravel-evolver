<?php

it('executes install command', function () {
    $this->artisan('app:install')
        ->assertSuccessful()
        ->expectsOutputToContain('installed');
});
