<?php

declare(strict_types=1);

namespace Infinity\Evolver\Database;

use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Deployment\ExecutionRecord;
use Infinity\Evolver\Exceptions\RepositoryUnavailableException;

/** Query-builder repository retaining the v2 schema so existing records stay authoritative. */
final class DatabaseActionRepository implements ActionRepository
{
    public const TABLE = 'evolutions';

    /**
     * Create a new database action repository instance.
     */
    public function __construct(private readonly Connection $connection) {}

    /**
     * Ensure the migrations have created the history table.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function ensureAvailable(): void
    {
        if (! $this->connection->getSchemaBuilder()->hasTable(self::TABLE)) {
            throw new RepositoryUnavailableException('The evolutions table is missing. Publish and run Evolver migrations first.');
        }
    }

    /**
     * Get the active execution history keyed by action identifier.
     *
     * @return array<string, ExecutionRecord>
     *
     * @throws RepositoryUnavailableException
     */
    public function all(): array
    {
        $this->ensureAvailable();
        $records = [];
        foreach ($this->connection->table(self::TABLE)->orderBy('id')->get() as $row) {
            $records[(string) $row->action_id] = new ExecutionRecord((string) $row->action_id, (string) $row->checksum, (string) $row->batch_id, new DateTimeImmutable((string) $row->ran_at), (int) $row->duration_ms, isset($row->target_version) ? (string) $row->target_version : null);
        }

        return $records;
    }

    /**
     * Generate a collision-resistant batch identifier without touching storage.
     */
    public function nextBatch(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Get the batch of the most recently inserted active record.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function latestBatch(): ?string
    {
        $this->ensureAvailable();
        $row = $this->connection->table(self::TABLE)->orderByDesc('id')->first();

        return $row === null ? null : (string) $row->batch_id;
    }

    /**
     * Record a successfully executed action.
     *
     * The row is inserted using the legacy-compatible v2 column names.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function record(string $id, string $checksum, string $batch, int $durationMs, ?string $version): void
    {
        $this->ensureAvailable();
        $this->connection->table(self::TABLE)->insert(['batch_id' => $batch, 'action_id' => $id, 'checksum' => $checksum, 'target_version' => $version, 'duration_ms' => $durationMs, 'ran_at' => now()]);
    }

    /**
     * Delete an action's active record after its down operation succeeds.
     *
     *
     * @throws RepositoryUnavailableException
     */
    public function remove(string $id): void
    {
        $this->ensureAvailable();
        $this->connection->table(self::TABLE)->where('action_id', $id)->delete();
    }
}
