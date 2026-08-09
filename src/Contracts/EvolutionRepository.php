<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

interface EvolutionRepository
{
    /**
     * Get committed action checksums keyed by action identity.
     *
     * @return array<string, string>
     */
    public function executed(): array;

    /**
     * Record a successfully committed action execution.
     */
    public function record(
        string $batchId,
        string $actionId,
        string $checksum,
        ?string $targetVersion,
        int $durationMs,
    ): void;
}
