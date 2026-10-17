<?php

declare(strict_types=1);

use Infinity\Evolver\Deployment\Action;
use Tests\Support\ActionLog;

return new class extends Action
{
    public function introducedIn(): ?string
    {
        return null;
    }

    public function expiresAt(): ?string
    {
        return '2.0.0';
    }

    public function up(): void
    {
        ActionLog::record('expired.up');
    }

    public function down(): void
    {
        ActionLog::record('expired.down');
    }
};
