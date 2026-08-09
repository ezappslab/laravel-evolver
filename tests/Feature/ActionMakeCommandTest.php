<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->actionsPath = base_path('tests/generated_actions');
    File::deleteDirectory($this->actionsPath);
    config(['evolver.actions_path' => $this->actionsPath]);
    Carbon::setTestNow('2026-08-09 12:34:56');
});

afterEach(function (): void {
    Carbon::setTestNow();
    File::deleteDirectory($this->actionsPath);
});

test('action command normalizes direct class name input and uses the package stub', function (): void {
    $this->artisan('evolver:action', ['name' => 'BackfillUserProfiles'])
        ->expectsOutputToContain('Action created: 2026_08_09_123456_backfill_user_profiles.php')
        ->assertSuccessful();

    $path = $this->actionsPath.'/2026_08_09_123456_backfill_user_profiles.php';
    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain('declare(strict_types=1)', 'public function handle(): void');
});

test('action command refuses silent overwrite', function (): void {
    $this->artisan('evolver:action', ['name' => 'SameAction'])->assertSuccessful();
    $this->artisan('evolver:action', ['name' => 'SameAction'])
        ->expectsOutputToContain('Action already exists')
        ->assertFailed();
});

test('action command rejects a name that cannot form a class', function (): void {
    $this->artisan('evolver:action', ['name' => '---'])
        ->expectsOutputToContain('valid PHP class name')
        ->assertFailed();
});
