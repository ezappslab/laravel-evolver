<?php

namespace Infinity\Evolver\Contracts;

abstract class Action
{
    /**
     * Retrieve the version or context where the subject was introduced.
     *
     * @return string|null Returns the version as a string, or null if unavailable.
     */
    public function introducedIn(): ?string
    {
        return null;
    }

    /**
     * Retrieves the version until which the action is required.
     *
     * @return string|null The upper version bound as a string, or null if not set.
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
