<?php

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Infinity\Evolver\Deploy\Deployer;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Version\VersionManager;

beforeEach(function () {
    $this->actionsPath = base_path('tests/deployer_actions');
    File::deleteDirectory($this->actionsPath);
    File::ensureDirectoryExists($this->actionsPath);
    File::put($this->actionsPath.'/001_first.php', '<?php return new class extends Infinity\Evolver\Contracts\Action { public function handle(): void {} };');
    config(['evolver.actions_path' => $this->actionsPath, 'evolver.versioning.strategy' => 'none']);
    $this->app->forgetInstance(VersionManager::class);

    $connection = $this->app->make(ConnectionInterface::class);
    $schema = $connection->getSchemaBuilder();
    $schema->dropIfExists('evolutions');
    $schema->create('evolutions', function (Blueprint $table): void {
        $table->id();
        $table->uuid('batch_id');
        $table->string('action_id')->unique();
        $table->string('checksum', 64);
        $table->string('target_version')->nullable();
        $table->unsignedInteger('duration_ms');
        $table->timestamp('ran_at');
        $table->timestamps();
    });
});

afterEach(fn () => File::deleteDirectory($this->actionsPath));

test('deployer coordinates the same plan through execution', function () {
    $deployer = $this->app->make(Deployer::class);
    $plan = $deployer->plan();
    $result = $deployer->deploy();

    expect($plan->actions[0]->status)->toBe(ActionStatus::Pending)
        ->and($result->execution->committedActionIds)->toBe(['001_first'])
        ->and($result->plan->actions[0]->descriptor->actionId)->toBe('001_first');
});
