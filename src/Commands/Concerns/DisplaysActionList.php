<?php

namespace Infinity\Evolver\Commands\Concerns;

use Illuminate\Support\Collection;
use Infinity\Evolver\Deploy\Planning\ActionStatus;

trait DisplaysActionList
{
    /**
     * @param  iterable<array{id: string, status: ActionStatus, duration: int|null, batch_id?: string|null}>  $actions
     */
    protected function displayActions(iterable $actions): void
    {
        $actions = $actions instanceof Collection ? $actions : collect($actions);

        $this->newLine();

        $this->components->twoColumnDetail('<fg=gray>Action name</>', '<fg=gray>Batch / Status</>');

        $actions->each(
            fn ($action) => $this->components->twoColumnDetail(
                $action['id'],
                $this->formatActionStatus($action)
            )
        );

        $this->newLine();
    }

    /**
     * @param  array{id: string, status: ActionStatus, duration: int|null, batch_id?: string|null}  $action
     */
    protected function formatActionStatus(array $action): string
    {
        $status = sprintf(
            '<fg=%s;options=bold>%s</>',
            $action['status']->color(),
            $this->actionStatusLabel($action['status'])
        );

        if (! empty($action['batch_id'])) {
            $status = '['.$action['batch_id'].'] '.$status;
        }

        return $status;
    }

    protected function actionStatusLabel(ActionStatus $status): string
    {
        return match ($status) {
            ActionStatus::AlreadyRan, ActionStatus::Success => 'Completed',
            ActionStatus::Pending => 'Pending',
            ActionStatus::OutOfRange => 'Out of range',
            ActionStatus::Changed => 'Changed',
            ActionStatus::Failure => 'Failed',
        };
    }
}
