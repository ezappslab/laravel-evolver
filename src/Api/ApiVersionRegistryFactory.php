<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api;

use DateTimeImmutable;
use Infinity\Evolver\Exceptions\InvalidApiVersionException;
use Throwable;

final class ApiVersionRegistryFactory
{
    /**
     * @param  array<string, mixed>  $versions
     */
    public function fromArray(array $versions): ApiVersionRegistry
    {
        $definitions = [];

        foreach ($versions as $value => $configuration) {
            if (! is_array($configuration)) {
                throw new InvalidApiVersionException("Configuration for API version [{$value}] must be an array.");
            }

            $version = new ApiVersion($value);
            $successor = $configuration['successor'] ?? null;
            $successorUrl = $configuration['successor_url'] ?? null;

            $definitions[] = new ApiVersionDefinition(
                $version,
                new ApiVersionLifecycle(
                    $this->date($configuration['deprecated_at'] ?? null, 'deprecated_at', $version),
                    $this->date($configuration['sunset_at'] ?? null, 'sunset_at', $version),
                ),
                is_string($successor) && $successor !== '' ? new ApiVersion($successor) : null,
                is_string($successorUrl) && $successorUrl !== '' ? $successorUrl : null,
            );
        }

        return new ApiVersionRegistry($definitions);
    }

    private function date(mixed $value, string $field, ApiVersion $version): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidApiVersionException(
                "API version [{$version->value}] lifecycle field [{$field}] must be a date string.",
            );
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            throw new InvalidApiVersionException(
                "Invalid [{$field}] date [{$value}] for API version [{$version->value}].",
                0,
                $exception,
            );
        }
    }
}
