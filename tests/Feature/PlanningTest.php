<?php

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\Action;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Deploy\Planning\ActionDescriptor;
use Infinity\Evolver\Deploy\Planning\ActionDiscovery;
use Infinity\Evolver\Deploy\Planning\ActionMaterializer;
use Infinity\Evolver\Deploy\Planning\ActionPlanBuilder;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Deploy\Planning\ApplicabilityPolicy;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Version\SemanticVersion;
use Infinity\Evolver\Version\TargetVersionResolverFactory;
use Infinity\Evolver\Version\VersionManager;

test('action materializer instantiates action from file', function () {
    $path = base_path('tests/temp_actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    $actionFile = $path.'/test_action.php';
    File::put($actionFile, '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function introducedIn(): ?string { return "1.0.0"; }
        public function handle(): void {} 
    };');

    $materializer = new ActionMaterializer;
    $descriptor = new ActionDescriptor('test_action', realpath($actionFile), md5_file($actionFile));

    $action = $materializer->materialize($descriptor);

    expect($action)->toBeInstanceOf(Action::class);

    $metadata = $materializer->getMetadata($action);
    expect($metadata['introducedIn'])->toBe('1.0.0')
        ->and($metadata['requiredUntil'])->toBeNull();

    File::deleteDirectory($path);
});

test('action plan builder builds plan', function () {
    $path = base_path('tests/temp_actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    // 1. Action that should run
    File::put($path.'/run_me.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    // 2. Action that already ran
    File::put($path.'/already_ran.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    // 3. Action that is out of range
    File::put($path.'/out_of_range.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function introducedIn(): ?string { return "2.0.0"; }
        public function handle(): void {} 
    };');

    $discovery = new ActionDiscovery($path);
    $materializer = new ActionMaterializer;

    $repository = Mockery::mock(ActionRepository::class);
    $repository->shouldReceive('getSuccessfulRunChecksum')
        ->with('run_me')
        ->andReturn(null);
    $repository->shouldReceive('getSuccessfulRunChecksum')
        ->with('already_ran')
        ->andReturn(md5_file($path.'/already_ran.php'));
    $repository->shouldReceive('getSuccessfulRunChecksum')
        ->with('out_of_range')
        ->andReturn(null);

    $versionManager = new VersionManager($repository, new TargetVersionResolverFactory);
    $repository->shouldReceive('getCurrentVersion')->andReturn('1.0.0');

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '1.1.0',
    ]);

    $policy = new ApplicabilityPolicy;

    $builder = new ActionPlanBuilder($discovery, $materializer, $repository, $versionManager, $policy);
    $plan = $builder->build();

    expect($plan->toRun)->toHaveCount(1)
        ->and($plan->toRun[0]->actionId)->toBe('run_me')
        ->and($plan->skipped)->toHaveCount(2)
        ->and($plan->skipped[0]['descriptor']->actionId)->toBe('already_ran')
        ->and($plan->skipped[0]['status'])->toBe(ActionStatus::AlreadyRan)
        ->and($plan->skipped[1]['descriptor']->actionId)->toBe('out_of_range')
        ->and($plan->skipped[1]['status'])->toBe(ActionStatus::OutOfRange);

    File::deleteDirectory($path);
});

test('action plan builder throws exception on changed action if safety enabled', function () {
    $path = base_path('tests/temp_actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    File::put($path.'/changed.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    $discovery = new ActionDiscovery($path);
    $materializer = new ActionMaterializer;

    $repository = Mockery::mock(ActionRepository::class);
    $repository->shouldReceive('getSuccessfulRunChecksum')
        ->with('changed')
        ->andReturn('old-checksum');

    $versionManager = new VersionManager($repository, new TargetVersionResolverFactory);
    $repository->shouldReceive('getCurrentVersion')->andReturn('1.0.0');

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '1.1.0',
        'evolver.safety.fail_on_changed_action' => true,
    ]);

    $policy = new ApplicabilityPolicy;

    $builder = new ActionPlanBuilder($discovery, $materializer, $repository, $versionManager, $policy);

    expect(fn () => $builder->build())->toThrow(ActionChangedException::class);

    File::deleteDirectory($path);
});

test('action plan builder skips with "changed" status if safety disabled', function () {
    $path = base_path('tests/temp_actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    File::put($path.'/changed.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { 
        public function handle(): void {} 
    };');

    $discovery = new ActionDiscovery($path);
    $materializer = new ActionMaterializer;

    $repository = Mockery::mock(ActionRepository::class);
    $repository->shouldReceive('getSuccessfulRunChecksum')
        ->with('changed')
        ->andReturn('old-checksum');

    $versionManager = new VersionManager($repository, new TargetVersionResolverFactory);
    $repository->shouldReceive('getCurrentVersion')->andReturn('1.0.0');

    config([
        'evolver.versioning.target.resolver' => 'config',
        'evolver.versioning.target.config.key' => 'app.version',
        'app.version' => '1.1.0',
        'evolver.safety.fail_on_changed_action' => false,
    ]);

    $policy = new ApplicabilityPolicy;

    $builder = new ActionPlanBuilder($discovery, $materializer, $repository, $versionManager, $policy);
    $plan = $builder->build();

    expect($plan->skipped)->toHaveCount(1)
        ->and($plan->skipped[0]['status'])->toBe(ActionStatus::Changed);

    File::deleteDirectory($path);
});

test('action discovery finds php files and sorts them alphabetically', function () {
    $path = base_path('tests/temp_actions');
    if (! File::isDirectory($path)) {
        File::makeDirectory($path, 0755, true);
    }

    File::put($path.'/b_action.php', '<?php // b');
    File::put($path.'/a_action.php', '<?php // a');
    File::put($path.'/c_file.txt', 'not an action');

    $discovery = new ActionDiscovery($path);
    $actions = $discovery->discover();

    expect($actions)->toHaveCount(2)
        ->and($actions[0]->actionId)->toBe('a_action')
        ->and($actions[0]->path)->toBe(realpath($path.'/a_action.php'))
        ->and($actions[1]->actionId)->toBe('b_action')
        ->and($actions[1]->path)->toBe(realpath($path.'/b_action.php'));

    File::deleteDirectory($path);
});

test('action discovery returns empty array if directory does not exist', function () {
    $discovery = new ActionDiscovery(base_path('non_existent_path'));
    $actions = $discovery->discover();

    expect($actions)->toBeArray()->toBeEmpty();
});

test('applicability policy applies when target is inside version interval', function () {
    $policy = new ApplicabilityPolicy;

    $v1 = SemanticVersion::parse('1.0.0');
    $v2 = SemanticVersion::parse('2.0.0');
    $v3 = SemanticVersion::parse('3.0.0');

    // Case: No constraints
    expect($policy->applies(null, null, $v2))->toBeTrue();

    // Case: Introduced in 1.0.0, target 2.0.0 -> True
    expect($policy->applies($v1, null, $v2))->toBeTrue();

    // Case: Introduced in 2.0.0, target 2.0.0 -> True
    expect($policy->applies($v2, null, $v2))->toBeTrue();

    // Case: Introduced in 3.0.0, target 2.0.0 -> False
    expect($policy->applies($v3, null, $v2))->toBeFalse();

    // Case: Required until 3.0.0, target 2.0.0 -> True
    expect($policy->applies(null, $v3, $v2))->toBeTrue();

    // Case: Required until 2.0.0, target 2.0.0 -> False (exclusive upper bound)
    expect($policy->applies(null, $v2, $v2))->toBeFalse();

    // Case: Required until 1.0.0, target 2.0.0 -> False
    expect($policy->applies(null, $v1, $v2))->toBeFalse();

    // Case: Missing target -> False
    expect($policy->applies($v1, $v3, null))->toBeFalse();
});

test('applicability policy from config ignores removed mode setting', function () {
    config(['evolver.applicability.mode' => 'crossing']);
    $policy = ApplicabilityPolicy::fromConfig();

    $v1 = SemanticVersion::parse('1.0.0');
    $v2 = SemanticVersion::parse('2.0.0');

    expect($policy->applies($v1, $v2, $v2))->toBeFalse();
});
