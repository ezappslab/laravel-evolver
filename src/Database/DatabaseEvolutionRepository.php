<?php

declare(strict_types=1);

namespace Infinity\Evolver\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Exceptions\EvolutionTableMissingException;
use Infinity\Evolver\Models\Evolution;

final class DatabaseEvolutionRepository implements EvolutionRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Get committed action checksums keyed by action identity.
     *
     * @return array<string, string>
     */
    public function executed(): array
    {
        if (! $this->connection->getSchemaBuilder()->hasTable((new Evolution)->getTable())) {
            throw new EvolutionTableMissingException;
        }

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
            ->insert([
                'batch_id' => $batchId,
                'action_id' => $actionId,
                'checksum' => $checksum,
                'target_version' => $targetVersion,
                'duration_ms' => $durationMs,
                'ran_at' => now(),
            ]);
    }

    /**
     * Start an Evolution query on the configured connection.
     */
    private function query(): Builder
    {
        return $this->connection->table((new Evolution)->getTable());
    }
}
