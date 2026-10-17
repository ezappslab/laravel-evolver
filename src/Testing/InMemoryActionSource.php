<?php

declare(strict_types=1);

namespace Infinity\Evolver\Testing;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\ActionSource;
use Infinity\Evolver\Deployment\Action;
use Infinity\Evolver\Deployment\ActionDescriptor;
use Infinity\Evolver\Exceptions\InvalidActionException;

/** Serves registered action objects whose checksums derive from their identifier and revision. */
final class InMemoryActionSource implements ActionSource
{
    /**
     * The registered actions keyed by identifier.
     *
     * @var array<string, Action>
     */
    private array $actions = [];

    /**
     * The revision of each registered action, bumped by touch().
     *
     * @var array<string, int>
     */
    private array $revisions = [];

    /**
     * Create a new in-memory action source instance.
     *
     * @param  array<string, Action>  $actions
     *
     * @throws InvalidActionException
     */
    public function __construct(array $actions = [])
    {
        foreach ($actions as $id => $action) {
            $this->add($id, $action);
        }
    }

    /**
     * Register an action, rejecting identities that differ only by case.
     *
     *
     * @throws InvalidActionException
     */
    public function add(string $id, Action $action): void
    {
        foreach (collect($this->actions)->keys() as $existing) {
            if (Str::lower($existing) === Str::lower($id)) {
                throw new InvalidActionException("Duplicate action identity [{$id}].");
            }
        }

        $this->actions[$id] = $action;
        $this->revisions[$id] = 0;
    }

    /**
     * Change the action's checksum, as if its source had been edited.
     *
     *
     * @throws \InvalidArgumentException
     */
    public function touch(string $id): void
    {
        if (! isset($this->revisions[$id])) {
            throw new \InvalidArgumentException("Action [{$id}] is not registered.");
        }

        $this->revisions[$id]++;
    }

    /**
     * Remove the action from discovery, as if its source had been deleted.
     */
    public function forget(string $id): void
    {
        unset($this->actions[$id], $this->revisions[$id]);
    }

    /**
     * Determine whether the action is currently registered.
     */
    public function has(string $id): bool
    {
        return isset($this->actions[$id]);
    }

    /**
     * Describe the registered actions in identifier order.
     *
     * @param  list<string>|null  $only
     * @return list<ActionDescriptor>
     */
    public function discover(?array $only = null): array
    {
        $ids = collect($this->actions)->keys()->all();
        if ($only !== null) {
            $ids = collect(Arr::where($ids, static fn (string $id): bool => collect($only)->containsStrict($id)))->values()->all();
        }
        $ids = collect(Arr::sort($ids))->values()->all();

        return Arr::map($ids, fn (string $id): ActionDescriptor => new ActionDescriptor($id, null, $this->hash($id), $this->actions[$id]));
    }

    /**
     * Get the action's checksum for its current revision.
     *
     *
     * @throws InvalidActionException
     */
    public function checksum(ActionDescriptor $action): string
    {
        if (! $this->has($action->id)) {
            throw new InvalidActionException("Unable to read action [{$action->id}].");
        }

        return $this->hash($action->id);
    }

    /**
     * Compute the checksum of the action's current revision.
     */
    private function hash(string $id): string
    {
        return hash('sha256', $id.'#'.$this->revisions[$id]);
    }
}
