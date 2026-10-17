<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Infinity\Evolver\Deployment\Action;
use Infinity\Evolver\Deployment\ActionDescriptor;
use Infinity\Evolver\Deployment\ActionDiscovery;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Support\RecordedAction;

/**
 * Mock a filesystem whose action directory lists the given file names.
 *
 * @param  list<string>  $names
 */
function actionFiles(array $names): Filesystem
{
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('isDirectory')->with('/actions')->andReturnTrue();
    $files->shouldReceive('files')->with('/actions')->andReturn(array_map(fn (string $name) => new SplFileInfo('/actions/'.$name, '', $name), $names));

    return $files;
}

it('discovers PHP fixtures in identifier order with file checksums', function (): void {
    $path = fixtures('deploy');
    $actions = (new ActionDiscovery(new Filesystem, $path))->discover();

    expect(array_map(fn (ActionDescriptor $a) => $a->id, $actions))->toBe(['2026_01_01_000001_first', '2026_01_01_000002_second'])
        ->and($actions[0]->checksum)->toBe(hash_file('sha256', $path.'/2026_01_01_000001_first.php'))
        ->and($actions[1]->checksum)->toBe(hash_file('sha256', $path.'/2026_01_01_000002_second.php'))
        ->and($actions[0]->action())->toBeInstanceOf(Action::class);
});

it('filters discovery to the given identifiers', function (): void {
    $discovery = new ActionDiscovery(new Filesystem, fixtures('deploy'));

    expect(array_map(fn (ActionDescriptor $a) => $a->id, $discovery->discover(['2026_01_01_000002_second', 'unknown'])))->toBe(['2026_01_01_000002_second'])
        ->and($discovery->discover([]))->toBeEmpty();
});

it('discovers nothing when the directory is missing', function (): void {
    expect((new ActionDiscovery(new Filesystem, fixtures('missing')))->discover())->toBeEmpty();
});

it('sorts by identifier and ignores non-PHP files', function (): void {
    $files = actionFiles(['002_b.php', 'README.md', '001_a.php', '003_c.PHP']);
    $files->shouldReceive('hash')->andReturn('checksum');

    expect(array_map(fn (ActionDescriptor $a) => $a->id, (new ActionDiscovery($files, '/actions'))->discover()))->toBe(['001_a', '002_b']);
});

it('rejects identities that differ only by case', function (?array $only): void {
    $files = actionFiles(['001_a.php', '001_A.php']);
    $files->shouldReceive('hash')->andReturn('checksum');

    expect(fn () => (new ActionDiscovery($files, '/actions'))->discover($only))->toThrow(InvalidActionException::class, 'Duplicate action identity [001_A].');
})->with([
    'all actions' => [null],
    'filtered to other actions' => [['002_other']],
]);

it('hashes files by pathname without loading them', function (): void {
    $files = actionFiles(['001_a.php']);
    $files->shouldReceive('hash')->once()->with('/actions/001_a.php', 'sha256')->andReturn('checksum');
    $files->shouldNotReceive('getRequire');

    $actions = (new ActionDiscovery($files, '/actions'))->discover();

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->path)->toBe('/actions/001_a.php')
        ->and($actions[0]->checksum)->toBe('checksum');
});

it('loads an action once on first use', function (): void {
    $files = actionFiles(['001_a.php']);
    $files->shouldReceive('hash')->andReturn('checksum');
    $files->shouldReceive('getRequire')->once()->with('/actions/001_a.php')->andReturn($action = new RecordedAction('a'));
    [$descriptor] = (new ActionDiscovery($files, '/actions'))->discover();

    expect($descriptor->action())->toBe($action)
        ->and($descriptor->action())->toBe($action);
});

it('rejects unreadable and broken action files', function (Closure $hash): void {
    $files = actionFiles(['001_a.php']);
    $hash($files->shouldReceive('hash')->once()->with('/actions/001_a.php', 'sha256'));

    expect(fn () => (new ActionDiscovery($files, '/actions'))->discover())->toThrow(InvalidActionException::class, 'Unable to read action [001_a].');
})->with([
    'hash returns false' => [fn ($expectation) => $expectation->andReturnFalse()],
    'hash raises a warning' => [fn ($expectation) => $expectation->andThrow(new ErrorException('hash_file(/actions/001_a.php): Failed to open stream'))],
]);

it('rejects files that cannot be loaded', function (string $scenario, string $message): void {
    [$descriptor] = (new ActionDiscovery(new Filesystem, fixtures($scenario)))->discover();

    expect(fn () => $descriptor->action())->toThrow(InvalidActionException::class, $message);
})->with([
    ['invalid/throws', 'Unable to load action [2026_01_01_000001_throws].'],
    ['invalid/non-action', 'must return an instance of '.Action::class.'.'],
]);

it('recomputes checksums from the source file', function (): void {
    $files = actionFiles(['001_a.php']);
    $files->shouldReceive('hash')->twice()->with('/actions/001_a.php', 'sha256')->andReturn('planned', 'edited');
    $discovery = new ActionDiscovery($files, '/actions');
    [$descriptor] = $discovery->discover();

    expect($descriptor->checksum)->toBe('planned')
        ->and($discovery->checksum($descriptor))->toBe('edited')
        ->and(fn () => $discovery->checksum(new ActionDescriptor('002_memory', null, 'checksum', new RecordedAction('memory'))))
        ->toThrow(InvalidActionException::class, 'Action [002_memory] has no source file.');
});
