# Laravel Evolver

Laravel Evolver discovers and executes deterministic, one-time application evolution actions for Laravel 12 and 13 on PHP 8.4.

## Installation

```bash
composer require ezappslab/laravel-evolver
php artisan evolver:install
php artisan migrate
```

## Actions

Generate an action with a PHP class-style name:

```bash
php artisan evolver:action BackfillUserProfiles
```

The command writes a timestamped file such as `2026_08_09_123456_backfill_user_profiles.php` to `evolver.actions_path`. The filename without `.php` is its stable database identity. Files are always planned in lexical filename order.

An action returns an anonymous class extending `Infinity\Evolver\Contracts\Action`:

```php
<?php

declare(strict_types=1);

use Infinity\Evolver\Contracts\Action;

return new class extends Action
{
    public function introducedIn(): ?string
    {
        return '1.2.0';
    }

    public function requiredUntil(): ?string
    {
        return '2.0.0';
    }

    public function handle(): void
    {
        // Perform the one-time change.
    }
};
```

The interval includes `introducedIn` and excludes `requiredUntil`. Either bound may be `null`.
When both bounds are present, `introducedIn` must be earlier than `requiredUntil`.

## Configuration

`evolver.versioning.strategy` accepts the `VersionStrategy` enum or its scalar value:

- `none`: resolves no application version, disables version filtering, and makes every unexecuted action applicable.
- `file`: reads `versioning.file.path`.
- `config`: reads `versioning.config.key`.
- `json`: reads `versioning.json.path` at `versioning.json.key`.
- `git`: reads the latest Git tag and removes `versioning.git.strip_prefix`.

Non-`none` strategies require valid semantic versions, including optional prerelease and build metadata, and use semantic-version comparisons. If a configured source contains no version, `versioning.required` determines whether planning throws or produces no applicable actions. Unreadable, malformed, or otherwise invalid sources always fail planning.

Set `evolver.database.connection` to a Laravel connection name to use a dedicated connection. Evolution records, their migration, and package-managed transactions share this connection; a `null` value uses Laravel's default connection.

## Planning and status

```bash
php artisan evolver:status
php artisan evolver:deploy --dry-run
```

Both commands use the same authoritative Planner and display every discovered action:

- `pending`: unexecuted, applicable, and selected to run.
- `executed`: previously committed successfully and never selected again.
- `not_applicable`: unexecuted but outside the selected target-version interval.

An executed action whose SHA-256 checksum changes causes planning to fail when `safety.fail_on_changed_action` is enabled. Dry-run performs discovery, materialization, version resolution, and Evolution reads, but never invokes actions, starts an execution transaction, or writes application/Evolution state.

Before each pending action executes, Evolver recomputes its checksum and aborts if the file has changed or disappeared since planning. This execution-time integrity check is always enabled.

## API versioning

Evolver can register URL-based major API versions and manage their runtime lifecycle independently from the application version used for evolution actions. The feature is opt-in:

```php
'api' => [
    'enabled' => true,
    'base_path' => 'api',
    'versions' => [
        'v1' => [
            'routes' => base_path('routes/api/v1.php'),
            'middleware' => ['api'],
            'deprecated_at' => '2027-01-01T00:00:00Z',
            'sunset_at' => '2027-08-01T00:00:00Z',
            'successor' => 'v2',
            'successor_url' => '/api/v2',
        ],
        'v2' => [
            'routes' => base_path('routes/api/v2.php'),
            'middleware' => ['api'],
        ],
    ],
],
```

Routes in these files are exposed at `/api/v1`, `/api/v2`, and so on. API versions must use major identifiers such as `v1`; each successor must also be registered. A sunset date requires an earlier deprecation date.

Evolver loads every route file listed under `evolver.api.versions`; do not also register those files in Laravel's `bootstrap/app.php`. If all API routes are managed by Evolver, remove Laravel's default `api:` entry:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

You may keep the `api: __DIR__.'/../routes/api.php'` entry when the application intentionally has additional, unversioned API routes in that file. Evolver's versioned files, such as `routes/api/v1.php`, must remain separate to avoid duplicate route registration.

Controllers and other route handlers may resolve the request-scoped context:

```php
use Infinity\Evolver\Api\ApiVersionContext;

public function __invoke(ApiVersionContext $context): array
{
    return ['api_version' => $context->version()->value];
}
```

Deprecated versions emit `Deprecation`, `Sunset`, and optional successor `Link` headers. Sunset versions return `410 Gone`; malformed and unsupported versions return stable JSON errors. Unknown routes within a supported version return a stable `api_route_not_found` error.

Inspect configured lifecycle states with:

```bash
php artisan evolver:api-status
```

The URL resolver implements `ApiVersionResolver`, allowing applications to replace URL negotiation later without changing the registry, lifecycle, or request-context APIs.

## Deploying and transactions

```bash
php artisan evolver:deploy
```

Pending actions run sequentially in planned order and execution stops at the first exception. Only successful, committed executions receive an Evolution record. A unique action identity prevents duplicate records.

`evolver.transactions.mode` accepts:

- `none`: the package opens no transaction. Earlier action work and records remain after a later failure; the failed action may already have produced partial side effects.
- `per_action`: each action and its Evolution record share a transaction. Earlier actions remain committed; the failed action's work on Laravel's default connection rolls back.
- `entire_run`: every current-run action and record shares one transaction. Any failure rolls all of them back and reports no current-run action as committed.

These transaction guarantees apply only to writes using the connection selected by `evolver.database.connection`. Filesystem changes, network/API calls, queues, cache, mail, external services, and writes on other database connections are not rolled back. Avoid irreversible external effects when database rollback semantics are required.

## Development

```bash
composer test
composer lint
composer validate --no-check-publish
```

## License

The MIT License. See [LICENSE](LICENSE).
