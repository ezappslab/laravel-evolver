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

## Configuration

`evolver.versioning.strategy` accepts the `VersionStrategy` enum or its scalar value:

- `none`: resolves no application version, disables version filtering, and makes every unexecuted action applicable.
- `file`: reads `versioning.file.path`.
- `config`: reads `versioning.config.key`.
- `json`: reads `versioning.json.path` at `versioning.json.key`.
- `git`: reads the latest Git tag and removes `versioning.git.strip_prefix`.

Non-`none` strategies use semantic-version comparisons. If resolution fails, `versioning.required` determines whether planning throws or produces no applicable actions.

Evolution records and package-managed transactions use Laravel's default database connection.

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

## Deploying and transactions

```bash
php artisan evolver:deploy
```

Pending actions run sequentially in planned order and execution stops at the first exception. Only successful, committed executions receive an Evolution record. A unique action identity prevents duplicate records.

`evolver.transactions.mode` accepts:

- `none`: the package opens no transaction. Earlier action work and records remain after a later failure; the failed action may already have produced partial side effects.
- `per_action`: each action and its Evolution record share a transaction. Earlier actions remain committed; the failed action's work on Laravel's default connection rolls back.
- `entire_run`: every current-run action and record shares one transaction. Any failure rolls all of them back and reports no current-run action as committed.

These transaction guarantees apply only to writes using Laravel's default database connection. Filesystem changes, network/API calls, queues, cache, mail, external services, and writes on other database connections are not rolled back. Avoid irreversible external effects when database rollback semantics are required.

## Development

```bash
composer test
composer lint
composer validate --no-check-publish
```

## License

The MIT License. See [LICENSE](LICENSE).
