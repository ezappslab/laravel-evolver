<?php

use Infinity\Evolver\Deploy\Running\TransactionMode;

return [

    /*
    |--------------------------------------------------------------------------
    | Actions Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where your action files are located. These files
    | contain the logic for your data evolutions.
    |
    */

    'actions_path' => base_path('deploy/actions'),

    /*
    |--------------------------------------------------------------------------
    | Transactions Mode
    |--------------------------------------------------------------------------
    |
    | This setting determines the transaction boundary for running actions.
    | "per_action" will wrap each action in its own transaction.
    |
    | Supported: "per_action", "all", "none"
    |
    */

    'transactions' => [
        'mode' => TransactionMode::PerAction,
    ],

    /*
    |--------------------------------------------------------------------------
    | Versioning Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure how the evolver tracks the current version of
    | your application and what format it should use.
    |
    */

    'versioning' => [
        /*
        |--------------------------------------------------------------------------
        | Version Format
        |--------------------------------------------------------------------------
        |
        | Currently supported: "semver"
        |
        */
        'format' => 'semver',

        /*
        |--------------------------------------------------------------------------
        | Target Version Resolution
        |--------------------------------------------------------------------------
        |
        | Exactly ONE resolver is used at a time.
        | Supported resolvers: file, config, json, git
        |
        */
        'target' => [

            /*
            | Fail if the target version cannot be resolved.
            */
            'required' => true,

            /*
            | Resolver to use: file | config | json | git
            */
            'resolver' => 'file',

            /*
            |--------------------------------------------------
            | File resolver
            |--------------------------------------------------
            | Reads the version from a plain text file.
            | Example file content: 1.4.2
            |
            */
            'file' => [
                'path' => base_path('VERSION'),
            ],

            /*
            |--------------------------------------------------
            | Config resolver
            |--------------------------------------------------
            | Reads the version from a config key.
            | Example: config/app.php => 'version' => '1.4.2'
            |
            */
            'config' => [
                'key' => 'app.version',
            ],

            /*
            |--------------------------------------------------
            | JSON resolver
            |--------------------------------------------------
            | Reads the version from a JSON file using a dotted key.
            | Example: composer.json => { "version": "1.4.2" }
            |
            */
            'json' => [
                'path' => base_path('composer.json'),
                'key' => 'version',
            ],

            /*
            |--------------------------------------------------
            | Git resolver
            |--------------------------------------------------
            | Resolves the latest git tag.
            | Tags like "v1.4.2" will be normalized to "1.4.2".
            |
            */
            'git' => [
                'strip_prefix' => 'v',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Settings
    |--------------------------------------------------------------------------
    |
    | These settings help prevent accidental data loss or corruption by
    | adding checks before running actions.
    |
    */

    'safety' => [
        'fail_on_changed_action' => true,
    ],

];
