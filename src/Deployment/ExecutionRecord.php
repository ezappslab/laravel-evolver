<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

use DateTimeImmutable;

/** A successfully executed action reconstructed from persistent history. */
final readonly class ExecutionRecord
{
    /**
     * Create a new execution record instance.
     */
    public function __construct(public string $id, public string $checksum, public string $batch, public DateTimeImmutable $executedAt, public int $durationMs = 0, public ?string $version = null) {}
}
