<?php

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Version\VersionManager;

test('status displays every planned action and the selected version strategy', function () {
    $path = base_path('tests/status_actions');
    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);
    File::put($path.'/001_status.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { public function handle(): void {} };');
    config(['evolver.actions_path' => $path, 'evolver.versioning.strategy' => 'none']);
    $this->app->forgetInstance(VersionManager::class);
    $this->artisan('evolver:status')
        ->expectsOutputToContain('Version strategy: none; target: None')
        ->expectsTable(['Action', 'Introduced in', 'Required until', 'Status'], [['001_status', '—', '—', 'pending']])
        ->assertSuccessful();

    File::deleteDirectory($path);
});
