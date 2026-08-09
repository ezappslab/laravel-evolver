<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version;

final class SemanticVersion
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
    public function value(): string
    {
        return $this->version;
    }

    /**
     * Determine if the version is less than another version.
     */
    public function isLessThan(self $other): bool
    {
        return version_compare($this->value(), $other->value()) < 0;
    }

    /**
     * Normalize the version string.
     */
    protected function normalize(string $version): string
    {
        return str($version)->ltrim('vV')->value();
    }
}
