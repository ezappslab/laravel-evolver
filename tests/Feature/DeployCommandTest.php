<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Infinity\Evolver\Version\VersionManager;

beforeEach(function () {
    $this->actionsPath = base_path('tests/deploy_command_actions');
    $this->markerPath = base_path('tests/deploy_command_marker');
    File::deleteDirectory($this->actionsPath);
    File::delete($this->markerPath);
    File::ensureDirectoryExists($this->actionsPath);
    $marker = var_export($this->markerPath, true);
    $executedPath = $this->actionsPath.'/000_already_executed.php';
    File::put($executedPath, '<?php return new class extends Infinity\Evolver\Contracts\Action { public function handle(): void {} };');
    File::put($this->actionsPath.'/001_write_marker.php', "<?php return new class extends Infinity\\Evolver\\Contracts\\Action { public function handle(): void { file_put_contents({$marker}, 'ran'); } };");

    config([
        'evolver.actions_path' => $this->actionsPath,
        'evolver.versioning.strategy' => 'none',
        'evolver.transactions.mode' => 'per_action',
    ]);
    $this->app->forgetInstance(VersionManager::class);

    DB::table('evolutions')->insert([
        'batch_id' => '00000000-0000-0000-0000-000000000000',
        'action_id' => '000_already_executed',
        'checksum' => File::hash($executedPath, 'sha256'),
        'target_version' => null,
        'duration_ms' => 0,
        'ran_at' => now(),
    ]);

});

afterEach(function () {
    File::deleteDirectory($this->actionsPath);
    File::delete($this->markerPath);
});

test('dry run uses normal planning and has no execution side effects', function () {
    $transactionLevel = DB::connection()->transactionLevel();

    $this->artisan('evolver:deploy', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: no actions will be executed.')
        ->expectsOutputToContain('001_write_marker')
        ->doesntExpectOutputToContain('000_already_executed')
        ->assertSuccessful();

    expect(File::exists($this->markerPath))->toBeFalse()
        ->and(DB::table('evolutions')->count())->toBe(1)
        ->and(DB::connection()->transactionLevel())->toBe($transactionLevel);
});

test('deploy command executes and records the pending plan', function () {
    $this->artisan('evolver:deploy')
        ->expectsOutputToContain('001_write_marker')
        ->doesntExpectOutputToContain('000_already_executed')
        ->expectsOutputToContain('Committed 1 action(s).')
        ->expectsOutputToContain('Batch ID:')
        ->assertSuccessful();

    expect(File::get($this->markerPath))->toBe('ran')
        ->and(DB::table('evolutions')->where('action_id', '001_write_marker')->exists())->toBeTrue();
});
