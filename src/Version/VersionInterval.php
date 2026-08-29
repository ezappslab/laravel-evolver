<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version;

use Infinity\Evolver\Exceptions\VersionResolutionException;

final class VersionInterval
{
    /**
     * Create a half-open semantic-version interval.
     */
    public function __construct(
        public readonly ?SemanticVersion $introducedIn,
        public readonly ?SemanticVersion $requiredUntil,
    ) {
        if (
            $this->introducedIn !== null
            && $this->requiredUntil !== null
            && ! $this->introducedIn->isLessThan($this->requiredUntil)
        ) {
            throw new VersionResolutionException(
                "Invalid version interval: introducedIn [{$this->introducedIn->value()}] "
                ."must be less than requiredUntil [{$this->requiredUntil->value()}].",
            );
        }
    }

    /**
     * Determine whether the version belongs to the half-open interval.
     */
    public function contains(SemanticVersion $version): bool
    {
        if ($this->introducedIn !== null && $version->isLessThan($this->introducedIn)) {
            return false;
        }

        return $this->requiredUntil === null || $version->isLessThan($this->requiredUntil);
    }
}
