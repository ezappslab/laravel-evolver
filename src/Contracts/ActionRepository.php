<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

use Infinity\Evolver\Deployment\ExecutionRecord;
use Infinity\Evolver\Exceptions\RepositoryUnavailableException;

/** Persists successful action executions. */
interface ActionRepository
{
    /**
     * Get the active execution history keyed by stable action identity.
     *
     * @return array<string, ExecutionRecord>
     *
     * @throws RepositoryUnavailableException
     */
    public function all(): array;

    /**
     * Generate an identifier shared by every action in the next deployment.
     */
    public function nextBatch(): string;

    /**
     * Get the most recently committed batch, or null when history is empty.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function latestBatch(): ?string;

    /**
     * Record an action only after its forward operation succeeds.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function record(string $id, string $checksum, string $batch, int $durationMs, ?string $version): void;

    /**
     * Remove an active record only after the corresponding rollback succeeds.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function remove(string $id): void;

    /**
     * Ensure the configured repository table exists.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function ensureAvailable(): void;
}
