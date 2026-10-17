<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands\Concerns;

use Infinity\Evolver\Deployment\Action;

/** Renders one action per line with its version window, state, and batch. */
trait RendersActions
{
    /**
     * Render the action with its version window and state, and its batch below.
     *
     * The window is shown only for actions that are already loaded, so
     * rendering never loads an action's source.
     */
    protected function renderAction(string $id, ?Action $action, string $state, string $color, ?string $batch): void
    {
        $window = $this->window($action);

        $this->components->twoColumnDetail($id, ($window === null ? '' : "<fg=gray>{$window}</> ")."<fg={$color};options=bold>{$state}</>");
        if ($batch !== null) {
            $this->line("    <fg=gray>⤷ batch {$batch}</>");
        }
    }

    /**
     * Describe the action's version window, or null when it has none or was not loaded.
     */
    private function window(?Action $action): ?string
    {
        $min = $action?->introducedIn();
        $max = $action?->expiresAt();

        return match (true) {
            $min !== null && $max !== null => "{$min} → {$max}",
            $min !== null => "from {$min}",
            $max !== null => "until {$max}",
            default => null,
        };
    }
}
