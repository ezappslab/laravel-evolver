<?php

declare(strict_types=1);

namespace Tests\Support;

use Infinity\Evolver\Deployment\Action;

/** In-memory action that records its operations and exposes a configurable version window. */
final class RecordedAction extends Action
{
    /**
     * Create a new recorded action instance.
     */
    public function __construct(private readonly string $name, private readonly ?string $introducedIn = null, private readonly ?string $expiresAt = null) {}

    public function up(): void
    {
        ActionLog::record("{$this->name}.up");
    }

    public function down(): void
    {
        ActionLog::record("{$this->name}.down");
    }

    public function introducedIn(): ?string
    {
        return $this->introducedIn;
    }

    public function expiresAt(): ?string
    {
        return $this->expiresAt;
    }
}
