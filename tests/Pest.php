<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\ActionRepository;
use Tests\Support\ActionLog;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => ActionLog::reset())
    ->in('Feature');

pest()->extend(TestCase::class)
    ->beforeEach(fn () => ActionLog::reset())
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Get the path of a read-only action fixture scenario.
 */
function fixtures(string $scenario): string
{
    return __DIR__.'/Fixtures/actions/'.$scenario;
}

/**
 * Point action discovery at a read-only fixture scenario.
 */
function useFixtures(string $scenario): string
{
    $path = fixtures($scenario);
    config()->set('evolver.actions.path', $path);

    return $path;
}

/**
 * Insert a history row, as if the action had been deployed.
 */
function recordHistory(string $id, string $checksum, string $batch = '00000000-0000-0000-0000-000000000001'): void
{
    app(ActionRepository::class)->record($id, $checksum, $batch, 0, null);
}

/**
 * Run an Artisan command and get its non-empty output lines with dot leaders collapsed.
 *
 * @param  array<string, mixed>  $parameters
 * @return list<string>
 */
function commandLines(string $command, array $parameters = []): array
{
    Artisan::call($command, $parameters);

    return collect(Str::of(Artisan::output())->explode("\n"))->map(fn (string $line): string => Str::squish(Str::replaceMatches('/ \.{2,} /', ' ', $line)))->filter()->values()->all();
}
