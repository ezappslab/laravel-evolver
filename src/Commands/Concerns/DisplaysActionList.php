<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands\Concerns;

use Infinity\Evolver\Deploy\Planning\DeploymentPlan;

trait DisplaysActionList
{
    /**
     * Display every action in the deployment plan.
     */
    protected function displayPlan(DeploymentPlan $plan): void
    {
        if ($plan->actions === []) {
            $this->components->info('No actions found.');

            return;
        }

        $this->table(
            ['Action', 'Introduced in', 'Required until', 'Status'],
            array_map(static fn ($action): array => [
                $action->descriptor->actionId,
                $action->introducedIn ?? '—',
                $action->requiredUntil ?? '—',
                $action->status->value,
            ], $plan->actions),
        );
    }
}
