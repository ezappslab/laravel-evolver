# Laravel Evolver v3

Evolver executes deployment-time application changes exactly once. It offers deterministic plans, forward and backward actions, checksummed database history, atomic execution locking, optional application-version windows, and URL- or header-versioned Laravel routing.

## Features and requirements

Evolver supports PHP 8.4 and Laravel 12/13. It uses Laravel's container, database, cache locks, routing, console, and filesystem together with Spatie Laravel Package Tools. Package discovery loads configuration and registers publishable migrations, commands, the facade, and routing integration.

## Installation and migrations

```bash
composer require ezappslab/laravel-evolver
php artisan evolver:install
```

`evolver:install` publishes `config/evolver.php` and the Evolver migration, then offers to run your migrations. If you decline, run `php artisan migrate` before deploying.

The published migration creates `evolutions`. Evolver never creates or changes tables during service resolution or HTTP requests.

## Configuration

Important keys in `config/evolver.php` are:

| Key | Default |
|---|---|
| `actions.path` | `deploy/actions` |
| `database.connection` | Laravel default |
| `transactions.mode` | `action` |
| `versioning.application.resolver` | `none` |
| file/config/JSON sources | `VERSION`, `app.version`, `composer.json:version` |
| `versioning.application.resolvers.git.prefixes` | `['v', 'V']` |
| `versioning.api.resolver` | `url` |
| `versioning.api.resolvers.url.base_path` | `api` |
| `versioning.api.resolvers.header.name` | `X-API-Version` |
| `safety.fail_on_changed_action` | `true` |

`versioning.api.versions` lists versions that clients may request.

## Actions and execution planning

```bash
php artisan evolver:action backfill-product-slugs
```

The command creates a timestamped anonymous action. Its filename is its stable identity and forward ordering key.

```php
use Infinity\Evolver\Deployment\Action;

return new class extends Action {
    public function up(): void { /* apply */ }
    public function down(): void { /* reverse */ }
};
```

Planning loads action objects but never invokes `up()` or `down()`. Plans are immutable and dry runs use the same planner. Omitting `down()` makes rollback explicitly fail as irreversible.

## Deploy, status, and rollback

```bash
php artisan evolver:status
php artisan evolver:deploy
php artisan evolver:deploy --pretend
php artisan evolver:deploy --force
php artisan evolver:rollback
php artisan evolver:rollback --batch=<uuid> --pretend
```

Production commands confirm unless `--force` is used. Rollback defaults to the latest batch and reverses its actions in reverse order. History is added only after successful `up()` and removed only after successful `down()`.

## Transactions, locking, checksums, and errors

Transaction modes are `none`, `action` (default), and `batch`. History shares the configured database connection and transaction. Transactions cannot undo external APIs, files, or nontransactional DDL, so those actions should be recoverable.

One Laravel atomic cache lock spans final planning and execution, preventing concurrent deploy/rollback races. The cache store must support locks.

Successful actions store a SHA-256 source checksum. Changed or missing historical actions are reported as integrity failures; disabling `safety.fail_on_changed_action` permits changed sources but never causes recorded actions to rerun. Exceptions distinguish invalid actions, unavailable storage, resolution failures, locking, unsupported API versions, and execution failures. Public API errors do not expose internal paths.

## Optional version applicability

```php
public function introducedIn(): ?string { return '2.1.0'; } // inclusive
public function expiresAt(): ?string { return '3.0.0'; }    // exclusive
```

Unconstrained actions always apply. Constrained actions wait when no application version exists. Invalid or empty windows fail planning.

## Application version resolvers

Select `none`, `config`, `json`, `file`, `git`, or a class name. File/JSON/config locations are configurable. Git deterministically uses the latest tag reachable from `HEAD` (`git describe --tags --abbrev=0`) and strips a configured prefix; missing Git metadata fails clearly without shell interpolation.

Custom classes are container-resolved and implement:

```php
use Infinity\Evolver\Contracts\ApplicationVersionResolver;

final class ReleaseVersion implements ApplicationVersionResolver
{
    public function resolve(): ?string { return '3.0.0'; }
}
```

Set `versioning.application.resolver` to `ReleaseVersion::class`.

## API routing

```php
Route::apiVersion('v1', function (): void {
    Route::get('/products/{product}', ProductController::class);
});
Route::apiVersion('v2', base_path('routes/api/v2.php'), ['api', 'auth:sanctum']);
```

URL mode exposes `/api/v1/products`. Header mode accepts `/api/products` plus `X-API-Version: v2`; global middleware rewrites only the managed API base path before matching the ordinary internal `/api/v2/products` route. It preserves method, headers, body, query string, parameters, and the original public URI in `_evolver_external_uri`. Direct access to configured internal prefixes is rejected in header mode. Native controller dispatch, binding, middleware, authentication, authorization, named URLs, and route caching remain in use.

## Facade

```php
use Infinity\Evolver\Facades\Evolver;

$application = Evolver::applicationVersion();
$requestedApi = Evolver::apiVersion(request());
$status = app(Infinity\Evolver\EvolverManager::class)->deployment->status();
```

## Database persistence and upgrading to v3

Rows contain `action_id`, checksum, UUID batch, target version, duration, and execution time with uniqueness/indexes for integrity. V3 is a fresh PHP API: convert old actions from `handle()` to `up()` and add `down()` where reversible.

The v3 repository and migration deliberately retain the existing `evolutions` column names (`batch_id`, `target_version`, `ran_at`). Do not drop this table during upgrade: existing rows remain authoritative and prevent historical one-time actions from running again. Back up the database, deploy converted action files with unchanged identity filenames, publish the new configuration with `php artisan vendor:publish --tag=evolver-config`, and run migrations normally. Do not run `evolver:install` when upgrading: it would publish a second migration that creates `evolutions`.

## Testing

```bash
composer test
composer lint
composer validate --no-check-publish
```

The suite uses Pest and Orchestra Testbench; linting runs Pint, PHPStan, and Rector.

## License

MIT. See [LICENSE](LICENSE).
