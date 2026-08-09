<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

abstract class Action
{
    /**
     * Get the version in which the action became applicable.
     */
    public function introducedIn(): ?string
    {
        return null;
    }

    /**
     * Get the exclusive version until which the action is applicable.
     */
    public function requiredUntil(): ?string
    {
        return null;
    }

    /**
     * Execute the action.
     */
    abstract public function handle(): void;
}
