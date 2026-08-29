<?php

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\Action;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Deploy\Planning\ActionDescriptor;
use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Deploy\Planning\DeploymentPlan;
use Infinity\Evolver\Deploy\Running\ChecksumActionIntegrityVerifier;
use Infinity\Evolver\Deploy\Running\Runner;
use Infinity\Evolver\Deploy\Running\TransactionMode;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->actionPath = base_path('tests/integrity_action.php');
    $this->markerPath = base_path('tests/integrity_action_marker');
    File::put($this->actionPath, '<?php return "planned";');
    File::delete($this->markerPath);
});

afterEach(function (): void {
    File::delete($this->actionPath);
    File::delete($this->markerPath);
});

test('unchanged action passes the execution integrity check', function (): void {
    $descriptor = new ActionDescriptor(
        'integrity_action',
        $this->actionPath,
        File::hash($this->actionPath, 'sha256'),
    );

    (new ChecksumActionIntegrityVerifier)->verify($descriptor);

    expect(true)->toBeTrue();
});

test('action changed after planning is rejected before execution', function (): void {
    $descriptor = new ActionDescriptor(
        'integrity_action',
        $this->actionPath,
        File::hash($this->actionPath, 'sha256'),
    );
    File::put($this->actionPath, '<?php return "changed";');

    expect(fn () => (new ChecksumActionIntegrityVerifier)->verify($descriptor))
        ->toThrow(ActionChangedException::class, 'has changed');
});

test('action removed after planning is rejected before execution', function (): void {
    $descriptor = new ActionDescriptor(
        'integrity_action',
        $this->actionPath,
        File::hash($this->actionPath, 'sha256'),
    );
    File::delete($this->actionPath);

    expect(fn () => (new ChecksumActionIntegrityVerifier)->verify($descriptor))
        ->toThrow(InvalidActionException::class, 'not found before execution');
});

test('runner checks integrity immediately before invoking an action', function (): void {
    $descriptor = new ActionDescriptor(
        'integrity_action',
        $this->actionPath,
        File::hash($this->actionPath, 'sha256'),
    );
    $action = new class($this->markerPath) extends Action
    {
        public function __construct(
            private readonly string $markerPath,
        ) {}

        public function handle(): void
        {
            File::put($this->markerPath, 'executed');
        }
    };
    $plan = new DeploymentPlan([
        new ActionPlan($descriptor, $action, ActionStatus::Pending, null, null),
    ], null);
    File::put($this->actionPath, '<?php return "changed";');

    $repository = Mockery::mock(EvolutionRepository::class);
    $repository->shouldNotReceive('record');
    $connection = Mockery::mock(ConnectionInterface::class);
    $runner = new Runner(
        $repository,
        TransactionMode::None,
        $connection,
        new ChecksumActionIntegrityVerifier,
    );

    expect(fn () => $runner->run($plan->executable(), 'batch'))
        ->toThrow(ActionChangedException::class)
        ->and(File::exists($this->markerPath))->toBeFalse();
});
