<?php

declare(strict_types=1);

namespace Infinity\Evolver\Database;

use Illuminate\Database\Eloquent\Builder;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Models\Evolution;

final class DatabaseEvolutionRepository implements EvolutionRepository
{
    /**
     * Get committed action checksums keyed by action identity.
     *
     * @return array<string, string>
     */
    public function executed(): array
    {
        /** @var array<string, string> $executed */
        $executed = $this->query()
            ->pluck('checksum', 'action_id')
            ->all();

        return $executed;
    }

    /**
     * Record a successfully committed action execution.
     */
    public function record(
        string $batchId,
        string $actionId,
        string $checksum,
        ?string $targetVersion,
        int $durationMs,
    ): void {
        $this->query()
            ->create([
                'batch_id' => $batchId,
                'action_id' => $actionId,
                'checksum' => $checksum,
                'target_version' => $targetVersion,
                'duration_ms' => $durationMs,
                'ran_at' => now(),
            ]);
    }

    /**
     * Start an Evolution query on Laravel's default connection.
     *
     * @return Builder<Evolution>
     */
    private function query(): Builder
    {
        return Evolution::query();
    }
}
