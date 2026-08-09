<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version;

use Infinity\Evolver\Contracts\Version as VersionContract;

final class SemanticVersion implements VersionContract
{
    /**
     * The normalized semantic version value.
     */
    protected string $version;

    /**
     * Create a semantic version value.
     */
    public function __construct(string $version)
    {
        $this->version = $this->normalize($version);
    }

    /**
     * Parse the given version string.
     *
     * @return static
     */
    public static function parse(string $version): self
    {
        return new self($version);
    }

    /**
     * Get the string representation of the version.
     */
    public function value(): ?string
    {
        return $this->version;
    }

    /**
     * Compare the version with another version.
     */
    public function compareTo(VersionContract $other): int
    {
        return version_compare($this->value(), $other->value());
    }

    /**
     * Determine if the version is greater than or equal to another version.
     */
    public function isGreaterThanOrEqual(VersionContract $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    /**
     * Determine if the version is less than another version.
     */
    public function isLessThan(VersionContract $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /**
     * Normalize the version string.
     */
    protected function normalize(string $version): string
    {
        return str($version)->ltrim('vV')->value();
    }
}
