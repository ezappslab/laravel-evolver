<?php

namespace Infinity\Evolver\Contracts;

use Throwable;

interface ActionRepository
{
    /**
     * Get the current version of the application.
     */
    public function getCurrentVersion(): ?string;

    /**
     * Set the current version of the application.
     */
    public function setCurrentVersion(string $version): void;

    /**
     * Determine if an action has been successfully run.
     */
    public function hasSuccessfulRun(string $actionId): bool;

    /**
     * Get the checksum of a successful run for an action.
     */
    public function getSuccessfulRunChecksum(string $actionId): ?string;

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
    ): void;

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
    ): void;

    /**
     * List recent action runs.
     */
    public function listRuns(int $limit = 200): array;
}
