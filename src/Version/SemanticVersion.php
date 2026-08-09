<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version;

use Infinity\Evolver\Exceptions\VersionResolutionException;

final class SemanticVersion
{
    /**
     * The normalized semantic version value.
     */
    private readonly string $version;

    /**
     * Create a semantic version value.
     */
    public function __construct(string $version)
    {
        $this->version = $this->normalize($version);
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
        [$core, $preRelease] = $this->precedence();
        [$otherCore, $otherPreRelease] = $other->precedence();

        foreach ($core as $index => $part) {
            if ($part !== $otherCore[$index]) {
                return $part < $otherCore[$index];
            }
        }

        if ($preRelease === null) {
            return false;
        }

        if ($otherPreRelease === null) {
            return true;
        }

        return $this->comparePreRelease($preRelease, $otherPreRelease) < 0;
    }

    /**
     * Get the core and prerelease values that determine SemVer precedence.
     *
     * @return array{list<int>, list<string>|null}
     */
    private function precedence(): array
    {
        $withoutBuild = str($this->version)->before('+')->value();
        [$core, $preRelease] = array_pad(explode('-', $withoutBuild, 2), 2, null);

        return [
            array_map(static fn (string $part): int => (int) $part, explode('.', $core)),
            $preRelease === null ? null : explode('.', $preRelease),
        ];
    }

    /**
     * Compare two SemVer prerelease identifier lists.
     *
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function comparePreRelease(array $left, array $right): int
    {
        $length = max(count($left), count($right));

        for ($index = 0; $index < $length; $index++) {
            if (! isset($left[$index])) {
                return -1;
            }

            if (! isset($right[$index])) {
                return 1;
            }

            $leftNumeric = ctype_digit($left[$index]);
            $rightNumeric = ctype_digit($right[$index]);

            if ($leftNumeric && $rightNumeric) {
                $comparison = (int) $left[$index] <=> (int) $right[$index];
            } elseif ($leftNumeric) {
                $comparison = -1;
            } elseif ($rightNumeric) {
                $comparison = 1;
            } else {
                $comparison = strcmp($left[$index], $right[$index]);
            }

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    }

    /**
     * Normalize the version string.
     */
    private function normalize(string $version): string
    {
        $normalized = str($version)->ltrim('vV')->value();

        if (! preg_match(
            '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'.
            '(?:-(?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*)'.
            '(?:\.(?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*))*)?'.
            '(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/',
            $normalized,
        )) {
            throw new VersionResolutionException("Invalid semantic version: {$version}");
        }

        return $normalized;
    }
}
