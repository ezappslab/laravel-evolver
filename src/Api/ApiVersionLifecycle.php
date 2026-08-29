<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ApiVersionLifecycle
{
    public function __construct(
        public ?DateTimeImmutable $deprecatedAt = null,
        public ?DateTimeImmutable $sunsetAt = null,
    ) {
        if ($this->sunsetAt !== null && $this->deprecatedAt === null) {
            throw new InvalidArgumentException('An API sunset date requires a deprecation date.');
        }

        if ($this->deprecatedAt !== null && $this->sunsetAt !== null && $this->deprecatedAt >= $this->sunsetAt) {
            throw new InvalidArgumentException('An API deprecation date must precede its sunset date.');
        }
    }

    public function stateAt(DateTimeImmutable $date): ApiVersionState
    {
        if ($this->sunsetAt !== null && $date >= $this->sunsetAt) {
            return ApiVersionState::Sunset;
        }

        if ($this->deprecatedAt !== null && $date >= $this->deprecatedAt) {
            return ApiVersionState::Deprecated;
        }

        return ApiVersionState::Active;
    }
}
