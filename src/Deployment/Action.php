<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** One deployment operation; version bounds are inclusive-minimum/exclusive-maximum. */
abstract class Action
{
    /**
     * Apply the one-time deployment operation.
     */
    abstract public function up(): void;

    /**
     * Reverse the deployment operation.
     *
     * The default makes irreversible actions fail explicitly.
     *
     *
     * @throws \LogicException
     */
    public function down(): void
    {
        throw new \LogicException('This Evolver action is irreversible.');
    }

    /**
     * Get the inclusive application version at which this action becomes eligible.
     */
    public function introducedIn(): ?string
    {
        return null;
    }

    /**
     * Get the exclusive application version at which this action stops applying.
     */
    public function expiresAt(): ?string
    {
        return null;
    }
}
