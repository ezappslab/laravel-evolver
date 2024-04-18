<?php

it('executes upgrade command', function () {
    $this->artisan('app:upgrade')
        ->assertSuccessful()
        ->expectsOutputToContain('upgraded');
});
