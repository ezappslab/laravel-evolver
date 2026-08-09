<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

use Infinity\Evolver\Contracts\Action;

final class ActionPlan
{
    /**
     * Create a planned action entry.
     */
    public function __construct(
        public readonly ActionDescriptor $descriptor,
        public readonly Action $action,
        public readonly ActionStatus $status,
        public readonly ?string $introducedIn,
        public readonly ?string $requiredUntil,
    ) {}
}
