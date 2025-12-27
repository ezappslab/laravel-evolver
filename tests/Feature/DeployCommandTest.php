<?php

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\ActionRepository;

test('deploy command dry run', function () {
    $path = base_path('deploy/actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    File::put($path.'/2025_01_01_000000_dry_run_action.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '1.0.0',
    ]);

    $this->artisan('evolver:deploy', ['--dry-run' => true])
        ->expectsOutputToContain('Evolver Dry Run - Action Plan:')
        ->expectsOutputToContain('2025_01_01_000000_dry_run_action')
        ->assertExitCode(0);

    File::deleteDirectory($path);
});

test('deploy command executes actions', function () {
    $path = base_path('deploy/actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    File::put($path.'/2025_01_01_000000_execute_action.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {
            // Do something
        } 
    };');

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '1.1.0',
    ]);

    // Simulate production environment to trigger confirmation
    $this->app['env'] = 'production';

    $this->artisan('evolver:deploy')
        ->expectsConfirmation('Are you sure you want to run this command?', 'yes')
        ->expectsOutputToContain('Starting data evolutions...')
        ->expectsOutputToContain('Deployment completed successfully.')
        ->assertExitCode(0);

    $repo = app(ActionRepository::class);
    expect($repo->getCurrentVersion())->toBe('1.1.0');
    expect($repo->hasSuccessfulRun('2025_01_01_000000_execute_action'))->toBeTrue();

    File::deleteDirectory($path);
});

test('deploy command with force option', function () {
    $path = base_path('deploy/actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    File::put($path.'/2025_01_01_000000_force_action.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '1.2.0',
    ]);

    // Simulate production environment
    $this->app['env'] = 'production';

    $this->artisan('evolver:deploy', ['--force' => true])
        ->expectsOutputToContain('Starting data evolutions...')
        ->assertExitCode(0);

    File::deleteDirectory($path);
});

test('deploy command with allow-changed option', function () {
    $path = base_path('deploy/actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    $actionPath = $path.'/2025_01_01_000000_changed_action.php';
    File::put($actionPath, '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '1.3.0',
    ]);

    // First run
    $this->artisan('evolver:deploy', ['--force' => true]);

    // Change the action file
    File::put($actionPath, '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void { /* changed */ } 
    };');

    // Second run without allow-changed should fail in builder if configured,
    // but here we test the runner's check via deploy()
    // By default safety.fail_on_changed_action is true, so PlanBuilder will throw before Runner.

    // Actually, PlanBuilder throws ActionChangedException if safety.fail_on_changed_action is true.
    // If we want to test --allow-changed, we need to make sure the runner gets it.

    config(['evolver.safety.fail_on_changed_action' => false]);

    $this->artisan('evolver:deploy', ['--force' => true, '--allow-changed' => true])
        ->expectsOutputToContain('Starting data evolutions...')
        ->assertExitCode(0);

    File::deleteDirectory($path);
});
