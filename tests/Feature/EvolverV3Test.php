<?php

declare(strict_types=1);
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Infinity\Evolver\Contracts\ApplicationVersionResolver;
use Infinity\Evolver\Database\DatabaseActionRepository;
use Infinity\Evolver\Deployment\ActionDiscovery;
use Infinity\Evolver\Deployment\DeploymentManager;
use Infinity\Evolver\Deployment\Direction;
use Infinity\Evolver\Deployment\ExecutionPlan;
use Infinity\Evolver\Deployment\Planner;
use Infinity\Evolver\Deployment\Runner;
use Infinity\Evolver\EvolverManager;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\ActionExecutionException;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Exceptions\LockException;
use Infinity\Evolver\Exceptions\RepositoryUnavailableException;
use Infinity\Evolver\Routing\Resolvers\HeaderApiVersionResolver;
use Infinity\Evolver\Routing\Resolvers\UrlApiVersionResolver;
use Infinity\Evolver\Versioning\Resolvers\FileResolver;
use Infinity\Evolver\Versioning\Resolvers\JsonResolver;
use Infinity\Evolver\Versioning\VersionManager;

beforeEach(function (): void {
    Schema::dropIfExists('evolutions');
    Schema::create('evolutions', function (Blueprint $table): void {
        $table->id();
        $table->uuid('batch_id')->index();
        $table->string('action_id')->unique();
        $table->string('checksum', 64);
        $table->string('target_version')->nullable();
        $table->unsignedInteger('duration_ms');
        $table->timestamp('ran_at');
    });
    $this->actions = sys_get_temp_dir().'/evolver-'.bin2hex(random_bytes(5));
    mkdir($this->actions, 0777, true);
    config()->set('evolver.actions.path', $this->actions);
    config()->set('evolver.versioning.application.resolver', 'none');
    foreach ([ActionDiscovery::class, Planner::class, DeploymentManager::class] as $class) {
        app()->forgetInstance($class);
    }
});
afterEach(function (): void {
    foreach (glob($this->actions.'/*') ?: [] as $file) {
        unlink($file);
    }rmdir($this->actions);
});
function makeAction(string $dir, string $id, string $up = '', string $down = '', ?string $introduced = null): string
{
    $bound = $introduced === null ? '' : "public function introducedIn(): ?string{return '{$introduced}';}";
    $source = "<?php use Infinity\\Evolver\\Deployment\\Action;return new class extends Action{{$bound}public function up():void{{$up}}public function down():void{{$down}}};";
    $path = $dir.'/'.$id.'.php';
    file_put_contents($path, $source);

    return $path;
}
it('boots manager commands and route macro', function (): void {
    expect(app(EvolverManager::class))->toBeInstanceOf(EvolverManager::class)->and(array_keys(Artisan::all()))->toContain('evolver:install', 'evolver:action', 'evolver:status', 'evolver:deploy', 'evolver:rollback');
    Route::apiVersion('v1', fn () => Route::get('/health', fn () => 'ok')->name('v1.health'), []);
    Route::getRoutes()->refreshNameLookups();
    expect(Route::getRoutes()->getByName('v1.health')?->uri())->toBe('api/v1/health');
});
it('creates actions only from safe names', function (string $name, int $exitCode, int $files): void {
    $this->artisan('evolver:action', ['name' => $name])->assertExitCode($exitCode);
    expect(glob($this->actions.'/*.php'))->toHaveCount($files)
        ->and(glob(dirname($this->actions).'/*evil*.php'))->toBeEmpty();
})->with([
    ['Backfill Users', 0, 1],
    ['backfill-product-slugs', 0, 1],
    ['../evil', 1, 0],
    ['nested/evil', 1, 0],
    ['_evil', 1, 0],
    ['', 1, 0],
]);
it('deploys once in filename order and rolls back in reverse order', function (): void {
    makeAction($this->actions, '2026_01_01_000002_second', "file_put_contents('{$this->actions}/log','2',FILE_APPEND);", "file_put_contents('{$this->actions}/log','b',FILE_APPEND);");
    makeAction($this->actions, '2026_01_01_000001_first', "file_put_contents('{$this->actions}/log','1',FILE_APPEND);", "file_put_contents('{$this->actions}/log','a',FILE_APPEND);");
    $manager = app(DeploymentManager::class);
    $result = $manager->deploy();
    expect($result->direction)->toBe(Direction::Up)->and(file_get_contents($this->actions.'/log'))->toBe('12')->and($manager->plan()->actions)->toBeEmpty();
    $manager->rollback();
    expect(file_get_contents($this->actions.'/log'))->toBe('12ba')->and(Schema::getConnection()->table('evolutions')->count())->toBe(0);
});
it('rolls back without resolving the application version', function (): void {
    makeAction($this->actions, '2026_01_01_000001_first', '', "file_put_contents('{$this->actions}/log','a');");
    $manager = app(DeploymentManager::class);
    $manager->deploy();
    config()->set('evolver.versioning.application.resolver', 'file');
    config()->set('evolver.versioning.application.resolvers.file.path', $this->actions.'/MISSING');
    $result = $manager->rollback();
    expect($result->completed)->toBe(['2026_01_01_000001_first'])->and(file_get_contents($this->actions.'/log'))->toBe('a')
        ->and($manager->rollbackPlan()->applicationVersion)->toBeNull();
});
it('uses the real plan for dry runs without side effects', function (): void {
    makeAction($this->actions, '2026_01_01_000001_test', "file_put_contents('{$this->actions}/effect','x');");
    $plan = app(DeploymentManager::class)->deploy(true);
    expect($plan)->toBeInstanceOf(ExecutionPlan::class)->and($plan->actions)->toHaveCount(1)->and(file_exists($this->actions.'/effect'))->toBeFalse()->and(Schema::getConnection()->table('evolutions')->count())->toBe(0);
});
it('applies optional version constraints', function (): void {
    config()->set('app.version', '2.0.0');
    config()->set('evolver.versioning.application.resolver', 'config');
    makeAction($this->actions, '001_future', '', '', '3.0.0');
    makeAction($this->actions, '002_always');
    expect(array_map(fn ($a) => $a->descriptor->id, app(DeploymentManager::class)->plan()->actions))->toBe(['002_always']);
});
it('detects changed and missing historical actions', function (): void {
    $path = makeAction($this->actions, '001_test');
    $manager = app(DeploymentManager::class);
    $manager->deploy();
    file_put_contents($path, "\n// changed", FILE_APPEND);
    expect(fn () => $manager->plan())->toThrow(ActionChangedException::class);
    unlink($path);
    expect(fn () => $manager->plan())->toThrow(InvalidActionException::class);
});
it('never records failed operations', function (): void {
    makeAction($this->actions, '001_test', "throw new RuntimeException('failure');");
    expect(fn () => app(DeploymentManager::class)->deploy())->toThrow(ActionExecutionException::class)->and(Schema::getConnection()->table('evolutions')->count())->toBe(0);
});

it('preserves completed history in none mode and rolls batch history back', function (string $mode, int $records): void {
    config()->set('evolver.transactions.mode', $mode);
    app()->forgetInstance(Runner::class);
    app()->forgetInstance(DeploymentManager::class);
    makeAction($this->actions, '001_first');
    makeAction($this->actions, '002_failure', "throw new RuntimeException('failure');");

    expect(fn () => app(DeploymentManager::class)->deploy())->toThrow(ActionExecutionException::class)
        ->and(Schema::getConnection()->table('evolutions')->count())->toBe($records);
})->with([['none', 1], ['batch', 0]]);

it('caches only a confirmed history table', function (): void {
    $repository = new DatabaseActionRepository(Schema::getConnection());
    Schema::rename('evolutions', 'evolutions_tmp');
    expect(fn () => $repository->ensureAvailable())->toThrow(RepositoryUnavailableException::class);
    Schema::rename('evolutions_tmp', 'evolutions');
    $repository->ensureAvailable();
    Schema::drop('evolutions');
    expect(fn () => $repository->latestBatch())->toThrow(QueryException::class);
});
it('rejects concurrent deployment execution', function (): void {
    $lock = app(LockProvider::class)->lock('evolver:deployment', 30);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(DeploymentManager::class)->deploy())->toThrow(LockException::class);
    } finally {
        $lock->release();
    }
});
it('resolves application sources and custom classes', function (): void {
    $file = $this->actions.'/VERSION';
    file_put_contents($file, " 1.2.3\n");
    expect((new FileResolver(app('files'), $file))->resolve())->toBe('1.2.3');
    $json = $this->actions.'/version.json';
    file_put_contents($json, '{"release":{"version":"2.0.0"}}');
    expect((new JsonResolver(app('files'), $json, 'release.version'))->resolve())->toBe('2.0.0');
    $custom = new class implements ApplicationVersionResolver
    {
        public function resolve(): ?string
        {
            return '9.8.7';
        }
    };
    app()->instance($custom::class, $custom);
    config()->set('evolver.versioning.application.resolver', $custom::class);
    expect(app(VersionManager::class)->application())->toBe('9.8.7');
});
it('extracts URL and header versions', function (): void {
    expect((new UrlApiVersionResolver)->resolve(Request::create('/api/v2/users')))->toBe('v2')->and((new HeaderApiVersionResolver)->resolve(Request::create('/api/users', 'GET', [], [], [], ['HTTP_X_API_VERSION' => 'v3'])))->toBe('v3');
});
it('rewrites only managed header-versioned paths before matching', function (): void {
    config()->set('evolver.versioning.api.resolver', 'header');
    config()->set('evolver.versioning.api.versions', ['v1', 'v2']);
    Route::apiVersion('v2', fn () => Route::get('/users/{user}', fn (Request $request, string $user) => $user.'|'.$request->attributes->get('_evolver_external_uri')), []);
    Route::get('/ordinary', fn () => 'ordinary');
    $this->withHeader('X-API-Version', 'v2')->get('/api/users/42?ok=1')->assertOk()->assertSee('42|/api/users/42?ok=1');
    $this->get('/ordinary')->assertOk()->assertSee('ordinary');
    $this->withHeader('X-API-Version', 'v2')->get('/api/v2/users/42')->assertNotFound();
});
