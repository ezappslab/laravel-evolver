<?php

namespace Infinity\Evolver\Contracts;

interface Version
{
    /**
     * Parse the given version string.
     *
     * @return static
     */
    public static function parse(string $version): self;

    /**
     * Get the string representation of the version.
     */
    public function value(): ?string;

    /**
     * Compare the version with another version.
     */
    public function compareTo(self $other): int;

    /**
     * Determine if the version is greater than or equal to another version.
     */
    public function isGreaterThanOrEqual(self $other): bool;

    /**
     * Determine if the version is less than another version.
     */
    public function isLessThan(self $other): bool;
}
