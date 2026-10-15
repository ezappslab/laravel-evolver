<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment Actions
    |--------------------------------------------------------------------------
    |
    | Deployment actions are one-off PHP classes that run during a deploy,
    | similar to migrations. These options control where they are stored.
    |
    */

    'actions' => [

        /*
        |----------------------------------------------------------------------
        | Actions Path
        |----------------------------------------------------------------------
        |
        | Evolver discovers PHP action files in this directory. Timestamped
        | file names provide both the stable action identity and execution
        | order. The "evolver:action" command also writes new actions here.
        |
        */

        'path' => base_path('deploy/actions'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Evolver records every executed action in the database so it is never
    | run twice and can be rolled back by batch.
    |
    */

    'database' => [

        /*
        |----------------------------------------------------------------------
        | Database Connection
        |----------------------------------------------------------------------
        |
        | This connection stores execution history and supplies the
        | transaction boundary used by the runner. Null uses Laravel's
        | default database connection.
        |
        */

        'connection' => env('EVOLVER_DB_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    |
    | Actions may be wrapped in database transactions on the connection
    | configured above so a failure does not leave partial changes behind.
    |
    */

    'transactions' => [

        /*
        |----------------------------------------------------------------------
        | Transaction Mode
        |----------------------------------------------------------------------
        |
        | This option controls how action execution is wrapped in database
        | transactions.
        |
        | Supported: "none", "action", "batch"
        |
        | "none"   - Actions run without a package-managed transaction.
        | "action" - Each action and its history write share a transaction.
        | "batch"  - The entire plan and its history run in one transaction.
        |
        */

        'mode' => env('EVOLVER_TRANSACTION_MODE', 'action'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Version Resolution
    |--------------------------------------------------------------------------
    |
    | Application versions control optional action windows. API versions are
    | resolved independently from the URL or the configured request header.
    |
    */

    'versioning' => [

        /*
        |----------------------------------------------------------------------
        | Application Version
        |----------------------------------------------------------------------
        |
        | The resolved application version is compared against each action's
        | "introducedIn" (inclusive) and "expiresAt" (exclusive) window.
        | Actions without a window always run. When no version can be
        | resolved, actions declaring a window are skipped.
        |
        */

        'application' => [

            /*
            |------------------------------------------------------------------
            | Application Version Resolver
            |------------------------------------------------------------------
            |
            | The resolver used to determine the current application version.
            | You may also provide the class name of a custom implementation
            | of the ApplicationVersionResolver contract, which is resolved
            | from the container.
            |
            | Supported: "none", "file", "config", "json", "git"
            |
            */

            'resolver' => env('EVOLVER_VERSION_RESOLVER', 'none'),

            /*
            |------------------------------------------------------------------
            | Application Version Resolver Options
            |------------------------------------------------------------------
            |
            | Driver-specific options for the built-in resolvers. Only the
            | options of the selected resolver are read. The "none" resolver
            | has no options and never resolves a version.
            |
            */

            'resolvers' => [

                /*
                |--------------------------------------------------------------
                | File Resolver
                |--------------------------------------------------------------
                |
                | Reads the trimmed contents of the file at "path". A missing
                | file is an error, while an empty file resolves no version.
                |
                */

                'file' => [
                    'path' => base_path('VERSION'),
                ],

                /*
                |--------------------------------------------------------------
                | Config Resolver
                |--------------------------------------------------------------
                |
                | Reads the Laravel configuration value at the dotted "key".
                | A null or empty value resolves no version.
                |
                */

                'config' => [
                    'key' => 'app.version',
                ],

                /*
                |--------------------------------------------------------------
                | JSON Resolver
                |--------------------------------------------------------------
                |
                | Reads the dotted "key" from the JSON document at "path". A
                | missing file, malformed JSON, or missing key is an error.
                |
                */

                'json' => [
                    'path' => base_path('composer.json'),
                    'key' => 'version',
                ],

                /*
                |--------------------------------------------------------------
                | Git Resolver
                |--------------------------------------------------------------
                |
                | Uses the latest tag reachable from HEAD in the application's
                | repository. The first matching entry in "prefixes" is
                | removed from the tag, so "v1.2.0" resolves to "1.2.0".
                |
                */

                'git' => [
                    'prefixes' => ['v', 'V'],
                ],
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | API Version
        |----------------------------------------------------------------------
        |
        | Incoming API requests are resolved to a version before routing so
        | they reach the routes registered with Route::apiVersion(). The
        | resolved version is validated against the supported versions.
        |
        */

        'api' => [

            /*
            |------------------------------------------------------------------
            | API Version Resolver
            |------------------------------------------------------------------
            |
            | The strategy used to read the requested API version.
            |
            | Supported: "url", "header"
            |
            | "url"    - The version is the path segment after the base path,
            |            such as "api/v1/users".
            | "header" - The version is read from the configured header and
            |            the request is internally routed to its versioned
            |            path. Direct requests to versioned paths return 404.
            |
            */

            'resolver' => env('EVOLVER_API_RESOLVER', 'url'),

            /*
            |------------------------------------------------------------------
            | Supported API Versions
            |------------------------------------------------------------------
            |
            | Requests for a version outside this list are rejected. An empty
            | list accepts any well-formed version identifier.
            |
            */

            'versions' => ['v1'],

            /*
            |------------------------------------------------------------------
            | API Version Resolver Options
            |------------------------------------------------------------------
            |
            | Driver-specific options for the API version resolvers.
            |
            */

            'resolvers' => [

                /*
                |--------------------------------------------------------------
                | URL Resolver
                |--------------------------------------------------------------
                |
                | The "base_path" is the API route prefix. It is used by both
                | strategies to identify API requests and to build the
                | versioned route prefixes, such as "api/v1".
                |
                */

                'url' => [
                    'base_path' => 'api',
                ],

                /*
                |--------------------------------------------------------------
                | Header Resolver
                |--------------------------------------------------------------
                |
                | The "name" of the request header holding the API version.
                | Requests without this header receive a 400 response.
                |
                */

                'header' => [
                    'name' => 'X-API-Version',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Safety
    |--------------------------------------------------------------------------
    |
    | Evolver stores a SHA-256 checksum of each action when it executes, so
    | later edits to already executed actions can be detected.
    |
    */

    'safety' => [

        /*
        |----------------------------------------------------------------------
        | Fail On Changed Action
        |----------------------------------------------------------------------
        |
        | When enabled, deployment and rollback planning stop if the source
        | of a previously executed action no longer matches its stored
        | checksum. Disabling this allows changed actions to be ignored.
        |
        */

        'fail_on_changed_action' => true,
    ],
];
