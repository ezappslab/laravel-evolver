<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('it can generate an action file', function () {
    $path = base_path('deploy/actions');

    // Clean up if exists
    if (File::isDirectory($path)) {
        File::deleteDirectory($path);
    }

    Artisan::call('evolver:action', ['name' => 'test_action']);

    $files = File::files($path);
    expect($files)->toHaveCount(1);

    $filename = $files[0]->getFilename();
    expect($filename)->toEndWith('_test_action.php')
        ->and(str($filename)->isMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_test_action\.php$/'))->toBeTrue();

    $content = File::get($files[0]->getRealPath());
    expect($content)->toContain('return new class extends Action');

    // Cleanup
    File::deleteDirectory($path);
});

test('it uses configured path', function () {
    $customPath = base_path('custom/actions');
    config(['evolver.actions_path' => $customPath]);

    if (File::isDirectory($customPath)) {
        File::deleteDirectory($customPath);
    }

    Artisan::call('evolver:action', ['name' => 'custom_action']);

    expect(File::isDirectory($customPath))->toBeTrue();
    $files = File::files($customPath);
    expect($files)->toHaveCount(1)
        ->and($files[0]->getFilename())->toEndWith('_custom_action.php');

    // Cleanup
    File::deleteDirectory(base_path('custom'));
});

test('it throws if the action stub is missing', function () {
    $stubPath = __DIR__.'/../../resources/stubs/action.stub';
    $backupPath = __DIR__.'/../../resources/stubs/action.stub.bak';

    File::move($stubPath, $backupPath);

    try {
        expect(fn () => Artisan::call('evolver:action', ['name' => 'missing_stub']))
            ->toThrow(RuntimeException::class, 'Action stub not found');
    } finally {
        File::move($backupPath, $stubPath);
        File::deleteDirectory(base_path('deploy/actions'));
    }
});
