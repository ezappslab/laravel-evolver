<?php

declare(strict_types=1);

use Infinity\Evolver\Deployment\Action;
use Tests\Support\ActionLog;

return new class extends Action
{
    public function introducedIn(): ?string
    {
        return 'v1.0';
    }

    public function expiresAt(): ?string
    {
        return '3.0.0';
    }

    public function up(): void
    {
        ActionLog::record('window.up');
    }

    public function down(): void
    {
        ActionLog::record('window.down');
    }
};
