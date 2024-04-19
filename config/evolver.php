<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Install actions
    |--------------------------------------------------------------------------
    |
    | In the 'Install' settings, users can specify a list of actions
    | to be executed during the installation of a new application.
    |
    */

    'install' => [
        \Infinity\Evolver\Actions\CreateDefaultUser::class,

        // ...

    ],

    /*
    |--------------------------------------------------------------------------
    | Upgrade actions
    |--------------------------------------------------------------------------
    |
    | In the 'Upgrade' settings, users can define actions to be performed
    | whenever the application requires updates or modifications.
    |
    */

    'upgrade' => [

        // ...

    ],

];
