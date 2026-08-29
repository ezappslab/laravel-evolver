<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api;

use Infinity\Evolver\Exceptions\InvalidApiVersionException;

final readonly class ApiVersion
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower($value);

        if (! preg_match('/^v[1-9]\d*$/', $normalized)) {
            throw new InvalidApiVersionException("Invalid API version [{$value}]; expected a major version such as [v1].");
        }

        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
