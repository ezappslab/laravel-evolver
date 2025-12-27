<?php

namespace Infinity\Evolver\Database;

use Illuminate\Support\Facades\Cache;
use Infinity\Evolver\Contracts\ActionRepository as ActionRepositoryContract;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
use Infinity\Evolver\Models\Evolution;
use Throwable;

class ActionRepository implements ActionRepositoryContract
{
    protected const CURRENT_VERSION_CACHE_KEY = 'evolver.current_version';

    /**
     * Get the current version of the application.
     */
    public function getCurrentVersion(): ?string
    {
        return Cache::get(self::CURRENT_VERSION_CACHE_KEY);
    }

    /**
     * Set the current version of the application.
     */
    public function setCurrentVersion(string $version): void
    {
        Cache::forever(self::CURRENT_VERSION_CACHE_KEY, $version);
    }

    /**
     * Determine if an action has been successfully run.
     */
    public function hasSuccessfulRun(string $actionId): bool
    {
        return Evolution::query()
            ->where('action_id', $actionId)
            ->where('status', ActionStatus::Success->value)
            ->exists();
    }

    /**
     * Get the checksum of a successful run for an action.
     */
    public function getSuccessfulRunChecksum(string $actionId): ?string
    {
        return Evolution::query()
            ->where('action_id', $actionId)
            ->where('status', ActionStatus::Success->value)
            ->orderByDesc('ran_at')
            ->orderByDesc('id')
            ->value('checksum');
    }

    /**
     * Record a successful action run.
     */
    public function recordSuccess(
        string $batchId,
        string $actionId,
        string $checksum,
        ?string $introducedIn = null,
        ?string $requiredUntil = null,
        ?string $targetVersion = null,
        int $durationMs = 0
    ): void {
        Evolution::query()->create([
            'batch_id' => $batchId,
            'action_id' => $actionId,
            'checksum' => $checksum,
            'status' => ActionStatus::Success->value,
            'introduced_in' => $introducedIn,
            'required_until' => $requiredUntil,
            'target_version' => $targetVersion,
            'duration_ms' => $durationMs,
            'ran_at' => now(),
        ]);
    }

    /**
     * Record a failed action run.
     */
    public function recordFailure(
        string $batchId,
        string $actionId,
        string $checksum,
        ?string $introducedIn = null,
        ?string $requiredUntil = null,
        ?string $targetVersion = null,
        int $durationMs = 0,
        ?Throwable $exception = null
    ): void {
        Evolution::query()->create([
            'batch_id' => $batchId,
            'action_id' => $actionId,
            'checksum' => $checksum,
            'status' => ActionStatus::Failure->value,
            'introduced_in' => $introducedIn,
            'required_until' => $requiredUntil,
            'target_version' => $targetVersion,
            'duration_ms' => $durationMs,
            'exception' => $exception ? (string) $exception : null,
            'ran_at' => now(),
        ]);
    }

    /**
     * List recent action runs.
     */
    public function listRuns(int $limit = 200): array
    {
        return Evolution::query()
            ->orderByDesc('ran_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
