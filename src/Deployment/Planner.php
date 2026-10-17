<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Contracts\ActionSource;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\InvalidActionException;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Versioning\VersionManager;

/** Discovers, validates, and plans actions without changing persistent state. */
final class Planner
{
    /**
     * Create a new planner instance.
     */
    public function __construct(private readonly ActionSource $source, private readonly ActionRepository $repository, private readonly VersionManager $versions, private readonly bool $failOnChangedAction) {}

    /**
     * Classify every discovered and historical action without writing state.
     *
     *
     * @throws InvalidActionException
     * @throws VersionResolutionException
     */
    public function status(): DeploymentStatus
    {
        $discovered = $this->source->discover();
        $executed = $this->repository->all();
        $version = $this->versions->application();
        $pending = [];
        $changed = [];
        $seen = [];
        foreach ($discovered as $action) {
            $seen[$action->id] = true;
            $record = $executed[$action->id] ?? null;
            if ($record && ! hash_equals($record->checksum, $action->checksum)) {
                $changed[] = $action;
            } elseif (! $record && $this->applies($action, $version)) {
                $pending[] = $action;
            }
        }
        $missing = array_values(array_filter($executed, static fn (ExecutionRecord $record): bool => ! isset($seen[$record->id])));

        return new DeploymentStatus($discovered, $pending, $changed, $missing, $executed, $version);
    }

    /**
     * Build a deterministic forward plan and enforce source integrity.
     *
     *
     * @throws ActionChangedException
     * @throws InvalidActionException
     * @throws VersionResolutionException
     */
    public function deployment(): ExecutionPlan
    {
        $status = $this->status();
        if ($status->missing !== []) {
            throw new InvalidActionException('Executed action files are missing: '.implode(', ', array_map(static fn (ExecutionRecord $r): string => $r->id, $status->missing)).'.');
        }
        if ($this->failOnChangedAction && $status->changed !== []) {
            throw new ActionChangedException('Previously executed actions have changed: '.implode(', ', array_map(static fn (ActionDescriptor $a): string => $a->id, $status->changed)).'.');
        }

        return new ExecutionPlan(Direction::Up, array_map(static fn (ActionDescriptor $a): PlannedAction => new PlannedAction($a), $status->pending), $this->repository->nextBatch(), $status->applicationVersion);
    }

    /**
     * Build a reverse-ordered plan for a committed batch.
     *
     * Rollback cannot proceed without the original action source because its
     * down operation and checksum are required for a safe reversal. The
     * application version is not resolved because reversals never record it.
     * Each planned action is loaded here so a broken source fails planning,
     * including pretend runs, instead of surfacing mid-rollback.
     *
     *
     * @throws ActionChangedException
     * @throws InvalidActionException
     */
    public function rollback(?string $batch = null): ExecutionPlan
    {
        $batch ??= $this->repository->latestBatch();
        if ($batch === null) {
            return new ExecutionPlan(Direction::Down, [], '');
        }
        $records = $this->repository->forBatch($batch);
        $found = [];
        foreach ($this->source->discover(array_keys($records)) as $a) {
            $found[$a->id] = $a;
        }

        $actions = [];
        foreach ($records as $record) {
            $descriptor = $found[$record->id] ?? throw new InvalidActionException("Executed action [{$record->id}] is missing; rollback cannot continue.");
            if ($this->failOnChangedAction && ! hash_equals($record->checksum, $descriptor->checksum)) {
                throw new ActionChangedException("Executed action [{$record->id}] changed; rollback cannot continue.");
            }

            $descriptor->action();
            $actions[] = new PlannedAction($descriptor, $record);
        }
        usort($actions, static fn (PlannedAction $a, PlannedAction $b): int => $b->descriptor->id <=> $a->descriptor->id);

        return new ExecutionPlan(Direction::Down, $actions, $batch);
    }

    /**
     * Determine whether the action applies to the given application version.
     *
     * Evaluates the action's inclusive-minimum/exclusive-maximum version window.
     * Actions without a window always apply; windowed actions never apply when
     * no application version is resolved.
     *
     *
     * @throws InvalidActionException
     */
    private function applies(ActionDescriptor $descriptor, ?string $version): bool
    {
        $action = $descriptor->action();
        $min = $action->introducedIn();
        $max = $action->expiresAt();
        foreach ([$min, $max] as $constraint) {
            if ($constraint !== null && ! VersionManager::isValid($constraint)) {
                throw new InvalidActionException("Action [{$descriptor->id}] has invalid version [{$constraint}].");
            }
        }
        if ($min !== null && $max !== null && self::compare($min, $max, '>=')) {
            throw new InvalidActionException("Action [{$descriptor->id}] has an empty version window.");
        }
        if ($min === null && $max === null) {
            return true;
        }

        if ($version === null) {
            return false;
        }

        return ($min === null || self::compare($version, $min, '>='))
            && ($max === null || self::compare($version, $max, '<'));
    }

    /**
     * Compare two versions while ignoring an optional "v" prefix.
     */
    private static function compare(string $a, string $b, string $operator): bool
    {
        return version_compare(ltrim($a, 'vV'), ltrim($b, 'vV'), $operator);
    }
}
