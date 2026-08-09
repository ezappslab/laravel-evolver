<?php

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Deploy\Deployer;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Version\VersionManager;

beforeEach(function (): void {
    $this->actionsPath = base_path('tests/deployer_actions');
    File::deleteDirectory($this->actionsPath);
    File::ensureDirectoryExists($this->actionsPath);
    File::put($this->actionsPath.'/001_first.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { public function handle(): void {} };');
    config(['evolver.actions_path' => $this->actionsPath, 'evolver.versioning.strategy' => 'none']);
    $this->app->forgetInstance(VersionManager::class);
});

afterEach(fn () => File::deleteDirectory($this->actionsPath));

test('deployer coordinates the same plan through execution', function (): void {
    $deployer = $this->app->make(Deployer::class);
    $plan = $deployer->plan();
    $result = $deployer->deploy();

    expect($plan->actions[0]->status)->toBe(ActionStatus::Pending)
        ->and($result->execution->committedActionIds)->toBe(['001_first'])
        ->and($result->plan->actions[0]->descriptor->actionId)->toBe('001_first')
        ->and($result->plan->actions[0]->status)->toBe(ActionStatus::Executed);
});
