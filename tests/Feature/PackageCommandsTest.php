<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Infinity\Evolver\Commands\ActionCommand;
use Infinity\Evolver\EvolverManager;

it('boots manager commands and route macro', function (): void {
    expect(app(EvolverManager::class))->toBeInstanceOf(EvolverManager::class)->and(collect(Artisan::all())->keys()->all())->toContain('evolver:install', 'evolver:action', 'evolver:status', 'evolver:deploy', 'evolver:rollback');
    Route::apiVersion('v1', fn () => Route::get('/health', fn () => 'ok')->name('v1.health'), []);
    Route::getRoutes()->refreshNameLookups();
    expect(Route::getRoutes()->getByName('v1.health')?->uri())->toBe('api/v1/health');
});

it('ships a readable action stub', function (): void {
    $stub = dirname(__DIR__, 2).'/resources/stubs/action.stub';

    expect($stub)->toBeReadableFile()
        ->and(file_get_contents($stub))->toContain('return new class extends Action');
});

it('creates timestamped actions from the package stub', function (string $name, string $file): void {
    $this->travelTo(Carbon::parse('2026-01-02 03:04:05'));
    config()->set('evolver.actions.path', '/app/deploy/actions');
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('ensureDirectoryExists')->once()->with('/app/deploy/actions');
    $files->shouldReceive('exists')->once()->with('/app/deploy/actions/'.$file)->andReturnFalse();
    $files->shouldReceive('get')->once()->with(dirname(__DIR__, 2).'/resources/stubs/action.stub')->andReturn('<stub contents>');
    $files->shouldReceive('put')->once()->with('/app/deploy/actions/'.$file, '<stub contents>');
    Artisan::registerCommand(new ActionCommand($files));

    $this->artisan('evolver:action', ['name' => $name])
        ->expectsOutputToContain('/app/deploy/actions/'.$file)
        ->assertSuccessful();
})->with([
    ['Fix Users', '2026_01_02_030405_fix_users.php'],
    ['backfill-product-slugs', '2026_01_02_030405_backfill-product-slugs.php'],
]);

it('never overwrites an existing action', function (): void {
    $this->travelTo(Carbon::parse('2026-01-02 03:04:05'));
    config()->set('evolver.actions.path', '/app/deploy/actions');
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('ensureDirectoryExists')->once();
    $files->shouldReceive('exists')->once()->with('/app/deploy/actions/2026_01_02_030405_fix_users.php')->andReturnTrue();
    $files->shouldNotReceive('put');
    Artisan::registerCommand(new ActionCommand($files));

    $this->artisan('evolver:action', ['name' => 'Fix Users'])
        ->expectsOutputToContain('An action with this timestamp and name already exists.')
        ->assertFailed();
});

it('rejects names that could escape the actions directory', function (string $name): void {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldNotReceive('ensureDirectoryExists');
    $files->shouldNotReceive('put');
    Artisan::registerCommand(new ActionCommand($files));

    $this->artisan('evolver:action', ['name' => $name])
        ->expectsOutputToContain('The action name may only contain letters, numbers, spaces, underscores, and hyphens.')
        ->assertFailed();
})->with(['../evil', 'nested/evil', '_evil', '']);
