<?php

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Api\ApiVersionRegistry;
use Infinity\Evolver\Api\Resolvers\UrlApiVersionResolver;
use Infinity\Evolver\Api\Routing\ApiVersionRouteRegistrar;
use Infinity\Evolver\Contracts\ApiVersionResolver;

beforeEach(function (): void {
    $this->apiRoutesPath = base_path('tests/api-version-routes');
    File::deleteDirectory($this->apiRoutesPath);
    File::ensureDirectoryExists($this->apiRoutesPath);

    foreach (['v1', 'v2', 'v3'] as $version) {
        File::put($this->apiRoutesPath."/{$version}.php", <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;
use Infinity\Evolver\Api\ApiVersionContext;

Route::get('/context', static function (ApiVersionContext $context): array {
    return [
        'version' => $context->version()->value,
        'state' => $context->state->value,
    ];
});
PHP);
    }

    config([
        'evolver.api.base_path' => 'api',
        'evolver.api.versions' => [
            'v1' => [
                'routes' => $this->apiRoutesPath.'/v1.php',
                'middleware' => [],
                'deprecated_at' => '2000-01-01T00:00:00Z',
                'sunset_at' => '2999-01-01T00:00:00Z',
                'successor' => 'v2',
                'successor_url' => '/api/v2',
            ],
            'v2' => [
                'routes' => $this->apiRoutesPath.'/v2.php',
                'middleware' => [],
            ],
            'v3' => [
                'routes' => $this->apiRoutesPath.'/v3.php',
                'middleware' => [],
                'deprecated_at' => '1999-01-01T00:00:00Z',
                'sunset_at' => '2000-01-01T00:00:00Z',
            ],
        ],
    ]);

    foreach ([ApiVersionRegistry::class, ApiVersionResolver::class, ApiVersionRouteRegistrar::class] as $binding) {
        $this->app->forgetInstance($binding);
    }

    $this->app->make(ApiVersionRouteRegistrar::class)->registerConfigured();
});

afterEach(function (): void {
    File::deleteDirectory($this->apiRoutesPath);
});

test('versioned routes expose a request scoped api context', function (): void {
    $this->getJson('/api/v2/context')
        ->assertOk()
        ->assertExactJson(['version' => 'v2', 'state' => 'active']);
});

test('deprecated versions emit lifecycle and successor headers', function (): void {
    $this->getJson('/api/v1/context')
        ->assertOk()
        ->assertExactJson(['version' => 'v1', 'state' => 'deprecated'])
        ->assertHeader('Deprecation', 'true')
        ->assertHeader('Sunset', 'Tue, 01 Jan 2999 00:00:00 GMT')
        ->assertHeader('Link', '</api/v2>; rel="successor-version"');
});

test('sunset and unsupported versions return stable json errors', function (): void {
    $this->getJson('/api/v3/context')
        ->assertGone()
        ->assertJsonPath('error.code', 'sunset_api_version')
        ->assertJsonPath('error.version', 'v3');

    $this->getJson('/api/v9/context')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'unsupported_api_version')
        ->assertJsonPath('error.version', 'v9');

    $this->getJson('/api/not-a-version/context')
        ->assertBadRequest()
        ->assertJsonPath('error.code', 'invalid_api_version');
});

test('known versions return a stable error for unknown routes', function (): void {
    $this->getJson('/api/v2/missing')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'api_route_not_found');
});

test('url resolver ignores requests outside its configured base path', function (): void {
    expect((new UrlApiVersionResolver('api'))->resolve(request()->create('/web/v1')))->toBeNull();
});
