<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version;

use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;

final class VersionManager
{
    /**
     * Create the application version manager.
     */
    public function __construct(
        private readonly VersionStrategy $strategy,
        private readonly ?VersionResolver $resolver,
        private readonly bool $required,
    ) {}

    /**
     * Get the selected version strategy.
     */
    public function strategy(): VersionStrategy
    {
        return $this->strategy;
    }

    /**
     * Resolve and parse the target application version.
     */
    public function target(): ?SemanticVersion
    {
        if ($this->strategy === VersionStrategy::None) {
            return null;
        }

        $value = $this->resolver?->resolve();

        if (blank($value)) {
            if ($this->required) {
                throw new VersionResolutionException("Unable to resolve target version using strategy: {$this->strategy->value}");
            }

            return null;
        }

        return $this->parse($value);
    }

    /**
     * Determine whether version metadata filters action applicability.
     */
    public function filtersActions(): bool
    {
        return $this->strategy !== VersionStrategy::None;
    }

    /**
     * Parse a semantic version value.
     */
    public function parse(string $version): SemanticVersion
    {
        return SemanticVersion::parse($version);
    }
}
