<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Version detection type
    |--------------------------------------------------------------------------
    |
    | Users can specify a versioning detection method: 'config'
    | or 'version_control'. The 'config' option loads the version
    | from the 'version_key' in the config file. The 'version_control'
    | option retrieves the version directly from version control tags.
    |
    */

    'versioning_mode' => 'config',

    'version_key' => 'app.version',

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
