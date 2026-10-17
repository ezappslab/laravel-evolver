<?php

declare(strict_types=1);

use Infinity\Evolver\Deployment\Action;
use Tests\Support\ActionLog;

return new class extends Action
{
    public function introducedIn(): ?string
    {
        return '3.0.0';
    }

    public function expiresAt(): ?string
    {
        return null;
    }

    public function up(): void
    {
        ActionLog::record('future.up');
    }

    public function down(): void
    {
        ActionLog::record('future.down');
    }
};
