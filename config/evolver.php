<?php

use Infinity\Evolver\Deploy\Running\TransactionMode;
use Infinity\Evolver\Version\VersionStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | Actions Path
    |--------------------------------------------------------------------------
    |
    | This path contains the timestamped PHP action files discovered by the
    | planner. Their filenames provide stable identities and execution order.
    |
    */

    'actions_path' => base_path('deploy/actions'),

    /*
    |--------------------------------------------------------------------------
    | Transaction Mode
    |--------------------------------------------------------------------------
    |
    | Choose whether actions run without package-managed transactions, inside
    | one transaction per action, or inside one transaction for the entire run.
    | Supported modes are "none", "per_action", and "entire_run".
    |
    */

    'transactions' => [
        'mode' => TransactionMode::PerAction,
    ],

    /*
    |--------------------------------------------------------------------------
    | Version Strategy
    |--------------------------------------------------------------------------
    |
    | Exactly one strategy supplies the target application version. The "none"
    | strategy disables version filtering, so every unexecuted action applies.
    | Set "required" to false to tolerate an unresolved non-none strategy.
    |
    */

    'versioning' => [

        /*
        |--------------------------------------------------------------------------
        | Selected Strategy
        |--------------------------------------------------------------------------
        |
        | Select exactly one VersionStrategy. The None strategy resolves no target
        | version and makes every unexecuted action applicable in filename order.
        |
        */

        'strategy' => VersionStrategy::None,

        /*
        | Require a Resolved Version
        |--------------------------------------------------------------------------
        |
        | When enabled, an unresolved File, Config, Json, or Git strategy causes
        | planning to fail. This option has no effect on the None strategy.
        |
        */

        'required' => true,

        /*
        |--------------------------------------------------------------------------
        | File Strategy
        |--------------------------------------------------------------------------
        |
        | Read the target version from a plain-text file. The file should contain
        | one semantic version value, such as "1.4.2" or "v1.4.2".
        |
        */

        'file' => [
            'path' => base_path('VERSION'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Config Strategy
        |--------------------------------------------------------------------------
        |
        | Read the target version from the given dotted Laravel configuration key.
        | The resolved configuration value must be a string.
        |
        */

        'config' => [
            'key' => 'app.version',
        ],

        /*
        |--------------------------------------------------------------------------
        | JSON Strategy
        |--------------------------------------------------------------------------
        |
        | Read a JSON document and select its version using the configured dotted
        | key. Invalid JSON causes version resolution to fail immediately.
        |
        */

        'json' => [
            'path' => base_path('composer.json'),
            'key' => 'version',
        ],

        /*
        |--------------------------------------------------------------------------
        | Git Strategy
        |--------------------------------------------------------------------------
        |
        | Resolve the latest Git tag and remove the configured prefix when present.
        | For example, a "v" prefix normalizes "v1.4.2" to "1.4.2".
        |
        */

        'git' => [
            'strip_prefix' => 'v',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Safety
    |--------------------------------------------------------------------------
    |
    | When enabled, planning fails if a previously committed action file has a
    | different checksum, preventing changed actions from passing unnoticed.
    |
    */

    'safety' => [
        'fail_on_changed_action' => true,
    ],
];
