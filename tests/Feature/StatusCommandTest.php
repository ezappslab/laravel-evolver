<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Infinity\Evolver\Version\VersionManager;

test('status displays every planned action and the selected version strategy', function (): void {
    $path = base_path('tests/status_actions');
    File::deleteDirectory($path);
    File::ensureDirectoryExists($path);
    File::put($path.'/001_status.php', '<?php return new class extends Infinity\Evolver\Contracts\Action {
        public function introducedIn(): ?string { return "1.2.0"; }
        public function requiredUntil(): ?string { return "2.0.0"; }
        public function handle(): void {}
    };');
    File::put($path.'/002_unversioned.php', '<?php return new class extends Infinity\Evolver\Contracts\Action {
        public function handle(): void {}
    };');
    config(['evolver.actions_path' => $path, 'evolver.versioning.strategy' => 'none']);
    $this->app->forgetInstance(VersionManager::class);
    expect(Artisan::call('evolver:status'))->toBe(0)
        ->and(Artisan::output())->toContain(
            'Version strategy: none; target: None',
            'Action name',
            'Introduced in / Valid until / Status',
            '001_status',
            '1.2.0 / 2.0.0 / Pending',
            '002_unversioned',
            '- / - / Pending',
        );

    File::deleteDirectory($path);
});
