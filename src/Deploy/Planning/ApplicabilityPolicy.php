<?php

namespace Infinity\Evolver\Deploy\Planning;

use Infinity\Evolver\Contracts\Version;

class ApplicabilityPolicy
{
    /**
     * Create a new applicability policy instance.
     */
    public static function fromConfig(): self
    {
        return new self;
    }

    /**
     * Determine if an action applies given the version constraints.
     */
    public function applies(
        ?Version $introducedIn = null,
        ?Version $requiredUntil = null,
        ?Version $target = null
    ): bool {
        // If target is missing, we can't determine applicability against it
        if ($target === null) {
            return false;
        }

        // Action must be introduced at or before the target version
        if ($introducedIn !== null && $target->isLessThan($introducedIn)) {
            return false;
        }

        // Action is no longer required once the target version reaches the upper bound.
        if ($requiredUntil !== null && ! $target->isLessThan($requiredUntil)) {
            return false;
        }

        return true;
    }
}
