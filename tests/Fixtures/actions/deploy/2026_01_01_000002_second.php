<?php

declare(strict_types=1);

use Infinity\Evolver\Deployment\Action;
use Tests\Support\ActionLog;

return new class extends Action
{
    public function up(): void
    {
        ActionLog::record('second.up');
    }

    public function down(): void
    {
        ActionLog::record('second.down');
    }
};
