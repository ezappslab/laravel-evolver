<?php

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Deploy\Planning\ActionDiscovery;
use Infinity\Evolver\Deploy\Planning\ActionMaterializer;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Deploy\Planning\Planner;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Version\VersionManager;
use Infinity\Evolver\Version\VersionStrategy;

beforeEach(function () {
    $this->actionsPath = base_path('tests/temp_actions');
    File::deleteDirectory($this->actionsPath);
    File::ensureDirectoryExists($this->actionsPath);
});

afterEach(function () {
    File::deleteDirectory($this->actionsPath);
});

function writePlanningAction(string $path, string $name, ?string $introduced = null, ?string $until = null): void
{
    $introducedCode = var_export($introduced, true);
    $untilCode = var_export($until, true);
    File::put($path.'/'.$name.'.php', <<<PHP
<?php
return new class extends Infinity\Evolver\Contracts\Action {
    public function introducedIn(): ?string { return {$introducedCode}; }
    public function requiredUntil(): ?string { return {$untilCode}; }
    public function handle(): void {}
};
PHP);
}

function planningRepository(array $executed = []): EvolutionRepository
{
    $repository = Mockery::mock(EvolutionRepository::class);
    $repository->shouldReceive('executed')->once()->andReturn($executed);

    return $repository;
}

function plannerFor(string $path, EvolutionRepository $repository, VersionManager $versions, bool $failChanged = true): Planner
{
    return new Planner(
        new ActionDiscovery($path),
        new ActionMaterializer,
        $repository,
        $versions,
        $failChanged,
    );
}

test('planner discovers materializes statuses and orders every action deterministically', function () {
    writePlanningAction($this->actionsPath, '2026_02_future', '2.0.0');
    writePlanningAction($this->actionsPath, '2026_01_executed');
    writePlanningAction($this->actionsPath, '2026_03_pending', '1.0.0', '3.0.0');

    $checksum = md5_file($this->actionsPath.'/2026_01_executed.php');
    $versions = new VersionManager(
        VersionStrategy::Config,
        new class implements VersionResolver
        {
            public function resolve(): ?string
            {
                return '1.5.0';
            }
        },
        true,
    );

    $plan = plannerFor($this->actionsPath, planningRepository(['2026_01_executed' => $checksum]), $versions)->plan();

    expect(array_map(fn ($item) => $item->descriptor->actionId, $plan->actions))->toBe([
        '2026_01_executed', '2026_02_future', '2026_03_pending',
    ])->and(array_map(fn ($item) => $item->status, $plan->actions))->toBe([
        ActionStatus::Executed, ActionStatus::NotApplicable, ActionStatus::Pending,
    ])->and($plan->pending())->toHaveCount(1)
        ->and($plan->pending()[0]->action)->toBe($plan->actions[2]->action)
        ->and($plan->executable()->actions)->toBe([$plan->actions[2]])
        ->and($plan->only(['2026_01_executed'])->actions)->toBe([$plan->actions[0]]);
});

test('none strategy makes every unexecuted action pending in natural order', function () {
    writePlanningAction($this->actionsPath, 'b_action', '99.0.0');
    writePlanningAction($this->actionsPath, 'a_action', null, '0.0.1');

    $versions = new VersionManager(VersionStrategy::None, null, true);
    $plan = plannerFor($this->actionsPath, planningRepository(), $versions)->plan();

    expect(array_map(fn ($item) => $item->descriptor->actionId, $plan->pending()))->toBe(['a_action', 'b_action'])
        ->and($plan->targetVersion)->toBeNull();
});

test('planner rejects a changed committed action', function () {
    writePlanningAction($this->actionsPath, 'changed');
    $versions = new VersionManager(VersionStrategy::None, null, true);

    expect(fn () => plannerFor(
        $this->actionsPath,
        planningRepository(['changed' => 'old-checksum']),
        $versions,
    )->plan())->toThrow(ActionChangedException::class);
});

test('discovery ignores non php files and missing directories', function () {
    File::put($this->actionsPath.'/ignored.txt', 'x');

    expect((new ActionDiscovery($this->actionsPath))->discover())->toBe([])
        ->and((new ActionDiscovery($this->actionsPath.'/missing'))->discover())->toBe([]);
});
