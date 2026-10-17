<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Infinity\Evolver\Routing\ApiVersionContext;
use Infinity\Evolver\Routing\ResolveApiVersion;
use Infinity\Evolver\Routing\Resolvers\HeaderApiVersionResolver;
use Infinity\Evolver\Routing\Resolvers\UrlApiVersionResolver;
use Infinity\Evolver\Versioning\VersionManager;

it('extracts URL and header versions', function (): void {
    expect((new UrlApiVersionResolver)->resolve(Request::create('/api/v2/users')))->toBe('v2')->and((new HeaderApiVersionResolver)->resolve(Request::create('/api/users', 'GET', [], [], [], ['HTTP_X_API_VERSION' => 'v3'])))->toBe('v3');
});

it('shares one normalized API base path between routing and resolution', function (): void {
    config()->set('evolver.versioning.api.resolvers.url.base_path', '/public-api/');
    Route::apiVersion('v2', fn () => Route::get('ping', fn () => 'ok')->name('v2.ping'));
    Route::getRoutes()->refreshNameLookups();
    $versions = app(VersionManager::class);
    expect($versions->apiBasePath())->toBe('public-api')
        ->and(Route::getRoutes()->getByName('v2.ping')?->uri())->toBe('public-api/v2/ping')
        ->and($versions->api(Request::create('/public-api/v2/ping')))->toBe('v2');
});

it('rewrites only managed header-versioned paths before matching', function (): void {
    config()->set('evolver.versioning.api.resolver', 'header');
    config()->set('evolver.versioning.api.versions', ['v1', 'v2']);
    Route::apiVersion('v2', fn () => Route::get('/users/{user}', fn (Request $request, string $user) => $user.'|'.$request->attributes->get('_evolver_external_uri')), []);
    Route::get('/ordinary', fn () => 'ordinary');
    $this->withHeader('X-API-Version', 'v2')->get('/api/users/42?ok=1')->assertOk()->assertSee('42|/api/users/42?ok=1');
    $this->get('/ordinary')->assertOk()->assertSee('ordinary');
    $this->withHeader('X-API-Version', 'v2')->get('/api/v2/users/42')->assertNotFound();
});

it('scopes and resets the API version context between requests', function (): void {
    $context = app(ApiVersionContext::class);
    $context->set('v2');

    app()->forgetScopedInstances();

    $nextContext = app(ApiVersionContext::class);
    expect($nextContext)->not->toBe($context)
        ->and($nextContext->get())->toBeNull();

    $nextContext->set('v2');
    app(ResolveApiVersion::class)->handle(Request::create('/ordinary'), fn () => response(''));

    expect($nextContext->get())->toBeNull();
});

it('rewrites header-versioned requests without buffering the body', function (): void {
    config()->set('evolver.versioning.api.resolver', 'header');
    config()->set('evolver.versioning.api.versions', ['v2']);
    Route::apiVersion('v2', fn () => Route::post('/users', fn (Request $request) => $request->input('name').'|'.$request->path()), []);
    $this->withHeader('X-API-Version', 'v2')->postJson('/api/users?ok=1', ['name' => 'Ada'])->assertOk()->assertSee('Ada|api/v2/users');

    $body = fopen('php://temp', 'r+');
    fwrite($body, '{"name":"Ada"}');
    $request = Request::create('/api/users', 'POST', server: ['HTTP_X_API_VERSION' => 'v2', 'CONTENT_TYPE' => 'application/json'], content: $body);
    app(ResolveApiVersion::class)->handle($request, function (Request $internal) use ($body) {
        expect($internal->path())->toBe('api/v2/users')
            ->and($internal->getContent(true))->toBe($body)
            ->and($internal->input('name'))->toBe('Ada');

        return response('');
    });
});

it('renders header-mode rejections through the exception handler', function (): void {
    config()->set('evolver.versioning.api.resolver', 'header');
    config()->set('evolver.versioning.api.versions', ['v1', 'v2']);
    Route::apiVersion('v2', fn () => Route::get('/users', fn () => 'users'), []);
    $this->getJson('/api/users')->assertStatus(400)->assertJson(['message' => 'The API version header is required.']);
    $this->getJson('/api/users', ['X-API-Version' => 'v9'])->assertNotFound()->assertJson(['message' => 'The requested API version is unsupported.']);
    $this->getJson('/api/v2/users', ['X-API-Version' => 'v2'])->assertNotFound()->assertJsonStructure(['message']);
});
