<?php

namespace Infinity\Evolver\Deploy\Planning;

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\Action;
use Infinity\Evolver\Exceptions\InvalidActionException;

/**
 * Materializes an Action instance from an ActionDescriptor.
 */
class ActionMaterializer
{
    /**
     * Materialize the action from the given descriptor.
     *
     * @throws InvalidActionException
     */
    public function materialize(ActionDescriptor $descriptor): Action
    {
        if (! File::exists($descriptor->path)) {
            throw new InvalidActionException($descriptor->path, "Action file not found: {$descriptor->path}");
        }

        try {
            $action = File::getRequire($descriptor->path);
        } catch (\Throwable $e) {
            throw new InvalidActionException($descriptor->path, 'Failed to require action file', $e);
        }

        if (! $action instanceof Action) {
            throw new InvalidActionException($descriptor->path, "Action file must return an instance of Infinity\Evolver\Contracts\Action");
        }

        return $action;
    }

    /**
     * Get metadata from an action.
     *
     * @return array{introducedIn: ?string, requiredUntil: ?string}
     */
    public function getMetadata(Action $action): array
    {
        return [
            'introducedIn' => $action->introducedIn(),
            'requiredUntil' => $action->requiredUntil(),
        ];
    }
}
