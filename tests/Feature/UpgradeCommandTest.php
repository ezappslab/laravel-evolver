<?php

it('executes Upgrade command', function () {
    $this->artisan('app:upgrade')
        ->assertSuccessful()
        ->expectsOutput('Upgraded');
});
