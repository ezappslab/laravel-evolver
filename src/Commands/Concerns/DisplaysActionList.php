<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands\Concerns;

use Infinity\Evolver\Deploy\Planning\ActionPlan;
use Infinity\Evolver\Deploy\Planning\ActionStatus;
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

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=gray>Action name</>',
            '<fg=gray>Introduced in / Valid until / Status</>',
        );

        foreach ($plan->actions as $action) {
            $this->components->twoColumnDetail(
                $action->descriptor->actionId,
                $this->formatStatus($action),
            );
        }

        $this->newLine();
    }

    /**
     * Format an action status using Laravel's migration status style.
     */
    private function formatStatus(ActionPlan $action): string
    {
        $status = match ($action->status) {
            ActionStatus::Pending => '<fg=yellow;options=bold>Pending</>',
            ActionStatus::Executed => '<fg=green;options=bold>Executed</>',
            ActionStatus::NotApplicable => '<fg=gray;options=bold>Not applicable</>',
        };

        return implode(' / ', [
            $action->introducedIn ?? '-',
            $action->requiredUntil ?? '-',
            $status,
        ]);
    }
}
