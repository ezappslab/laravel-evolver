<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** Supported database transaction boundaries for plan execution. */
enum TransactionMode: string
{
    case None = 'none';
    case Action = 'action';
    case Batch = 'batch';

    /**
     * Resolve a configured enum or string and reject unsupported values.
     *
     *
     * @throws \InvalidArgumentException
     */
    public static function configured(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return (is_string($value) ? self::tryFrom($value) : null)
            ?? throw new \InvalidArgumentException('Invalid Evolver transaction mode.');
    }
}
