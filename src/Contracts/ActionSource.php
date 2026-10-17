<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

use Infinity\Evolver\Deployment\ActionDescriptor;
use Infinity\Evolver\Exceptions\InvalidActionException;

/** Supplies action descriptors and their current checksums to the planner and runner. */
interface ActionSource
{
    /**
     * Discover actions in deterministic identifier order.
     *
     * When identifiers are given, only the matching actions are described.
     *
     * @param  list<string>|null  $only
     * @return list<ActionDescriptor>
     *
     * @throws InvalidActionException
     */
    public function discover(?array $only = null): array;

    /**
     * Get the current checksum, used to detect edits made after planning.
     *
     *
     * @throws InvalidActionException
     */
    public function checksum(ActionDescriptor $action): string;
}
