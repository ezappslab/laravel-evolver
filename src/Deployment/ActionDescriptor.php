<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

use Infinity\Evolver\Exceptions\InvalidActionException;

/** Discovered action metadata whose action object is loaded on first use. */
final class ActionDescriptor
{
    /**
     * The loaded action, or the loader that produces it on first use.
     *
     * @var Action|\Closure(): Action
     */
    private Action|\Closure $action;

    /**
     * Create a new action descriptor instance.
     *
     * The path is owned by the action source; in-memory actions have none.
     *
     * @param  Action|\Closure(): Action  $action
     */
    public function __construct(public readonly string $id, public readonly ?string $path, public readonly string $checksum, Action|\Closure $action)
    {
        $this->action = $action;
    }

    /**
     * Get the action, loading its source on first access.
     *
     * Executed actions are only checksum-compared, so their source is never
     * loaded unless they are planned for rollback.
     *
     *
     * @throws InvalidActionException
     */
    public function action(): Action
    {
        if ($this->action instanceof \Closure) {
            $this->action = ($this->action)();
        }

        return $this->action;
    }

    /**
     * Get the action only if its source has already been loaded.
     *
     * Lets callers report on loaded actions without loading executed ones.
     */
    public function loaded(): ?Action
    {
        return $this->action instanceof Action ? $this->action : null;
    }
}
