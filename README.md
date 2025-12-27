# Laravel Evolver

Laravel Evolver is a migration-like system for application evolution actions, designed to handle one-time data transformations, API migrations, or any logic that needs to execute once based on your application's version.

### Why Laravel Evolver Exists

Standard Laravel migrations are excellent for structural database changes (DDL). However, they are often less ideal for data transformations, cleaning up legacy records, or seeding new mandatory data during a deployment (DML), especially when these actions depend on the application's version.

Laravel Evolver solves this by providing a dedicated system for **Evolution Actions**. Unlike migrations which focus on the database schema, Evolver focuses on the **application state**. It allows you to define actions that:
- Run only once per environment.
- Are tied to specific application versions (introduced in version X, required until version Y).
- Are tracked independently of database migrations.
- Are protected by checksums to prevent accidental re-execution if the logic changes.

### Core Concepts

- **Actions**: Executable PHP files (typically anonymous classes) containing the logic to be performed once.
- **Current Version vs. Target Version**: Evolver tracks the "current" version of your app in the cache and compares it with a "target" version (resolved from your environment, e.g., `composer.json` or a `VERSION` file).
- **Action Applicability**: Actions can define when they become active (`introducedIn`) and until which version they are required (`requiredUntil`).
- **Action Status Tracking**: Every action execution is logged, including its checksum, duration, and status.
- **Safety Against Modified Actions**: If an action's code changes after it has already run, Evolver will block execution to prevent unpredictable results.

### Installation

Install the package via Composer:

```bash
composer require ezappslab/laravel-evolver
```

Publish the configuration and migrations:

```bash
php artisan evolver:install
```

Or manually:

```bash
php artisan vendor:publish --tag="laravel-evolver-config"
php artisan vendor:publish --tag="laravel-evolver-migrations"
```

Run the migrations to create the necessary tracking tables:

```bash
php artisan migrate
```

### Configuration

The configuration file is located at `config/evolver.php`.

Key options:
- **actions_path**: Where your evolution action files live (default: `deploy/actions`).
- **action applicability**: Actions run when the target version is within the action's `introducedIn` / `requiredUntil` interval. Missing bounds are treated as open intervals.
- **transaction mode**: Defines the database transaction boundary (`per_action`, `all`, or `none`).
- **versioning target resolver**: Configures the resolver for your application's target version. Exactly one resolver is used at a time.
- **safety options**: Includes settings like `fail_on_changed_action` to ensure data integrity.

```php
// Example config snippet
'actions_path' => base_path('deploy/actions'),
'transactions' => [
    'mode' => \Infinity\Evolver\Deploy\Running\TransactionMode::PerAction,
],
```

### Defining Actions

Actions are stored in the directory defined in your config (default `deploy/actions`).

#### File Naming Convention
Actions follow a timestamped naming convention similar to migrations: `YYYY_MM_DD_HHMMSS_action_name.php`. You can generate one using:

```bash
php artisan evolver:action your_action_name
```

#### Action Structure
Actions use an anonymous class returning an implementation of the `Action` interface.

```php
<?php

use Infinity\Evolver\Contracts\Action;

return new class extends Action
{
    /**
     * Retrieve the version or context where the subject was introduced.
     */
    public function introducedIn(): ?string
    {
        return '1.1.0';
    }

    /**
     * Retrieves the version until which the action is required.
     */
    public function requiredUntil(): ?string
    {
        return '2.0.0';
    }

    /**
     * Execute the evolution action.
     */
    public function handle(): void
    {
        // Your logic here (e.g., updating data, calling APIs)
    }
};
```

### Running Evolver

To execute pending actions:

```bash
php artisan evolver:deploy
```

- `--dry-run`: Display the actions that would be executed without actually running them.
- `--force`: Force the operation to run when in production.
- confirmations: Evolver respects Laravel's production safety and will ask for confirmation unless `--force` is used.

### Checking Status

To see the current state of your application's evolution:

```bash
php artisan evolver:status
```

This command shows:
- The current version of the application.
- The resolved target version.
- A list of all discovered actions and their status.

### Action Statuses

- **pending**: The action is applicable and has not been run yet.
- **already_ran**: The action was successfully executed in a previous batch.
- **out_of_range**: The action is not applicable for the target version interval (either too old or too new).
- **changed**: The action's file content has changed since it was last successfully run.
- **failed**: The action encountered an error during its last execution attempt.

### Version Resolution

Evolver determines your application's target version using a **Resolver**. Only one resolver is active at any given time, configured in `config/evolver.php`.

Supported resolvers:
- **file**: Reads from a plain text file (e.g., `VERSION`).
- **config**: Reads from a Laravel config key (e.g., `app.version`).
- **json**: Reads from a JSON file (e.g., `version` field in `composer.json`).
- **git**: Uses the latest Git tag as the version.

### Safety Guarantees

- **One-time execution**: Evolver ensures an action is never executed twice unless explicitly allowed.
- **Checksum validation**: Protects against running logic that has changed since it was first recorded.
- **Transaction handling**: Actions can be wrapped in database transactions to ensure atomicity.

### Testing & CI

During testing, Evolver behaves predictably. You can point the `actions_path` to a dedicated test actions directory in your `phpunit.xml` or `TestCase.php`:

```php
config(['evolver.actions_path' => base_path('tests/Evolver/Actions')]);
```

Evolver will respect the versioning logic even in tests, allowing you to verify that your evolution actions behave correctly across different version transitions.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
