<?php

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\ActionRepository;

test('status command displays current and target versions and actions', function () {
    $path = base_path('deploy/actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    // 1. Action to run
    File::put($path.'/2025_01_01_000000_to_run.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    // 2. Already ran action
    File::put($path.'/2025_01_01_000000_already_ran.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '1.1.0',
    ]);

    $repo = app(ActionRepository::class);
    $repo->setCurrentVersion('1.0.0');
    $repo->recordSuccess('batch-1', '2025_01_01_000000_already_ran', md5_file($path.'/2025_01_01_000000_already_ran.php'), null, null, '1.0.0', 100);

    $this->artisan('evolver:status')
        ->expectsOutputToContain('Current Version: 1.0.0')
        ->expectsOutputToContain('Target Version: 1.1.0')
        ->expectsOutputToContain('2025_01_01_000000_to_run')
        ->expectsOutputToContain('2025_01_01_000000_already_ran')
        ->assertExitCode(0);

    File::deleteDirectory($path);
});

test('status command handles missing versions', function () {
    $path = base_path('deploy/actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'evolver.versioning.target.required' => false,
    ]);

    $this->artisan('evolver:status')
        ->expectsOutputToContain('Current Version: None')
        ->expectsOutputToContain('Target Version: None')
        ->assertExitCode(0);

    File::deleteDirectory($path);
});
