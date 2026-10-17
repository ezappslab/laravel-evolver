<?php

declare(strict_types=1);

namespace Tests\Support;

/** Records action side effects so fixtures and fakes stay free of their own state. */
final class ActionLog
{
    /**
     * The recorded entries in call order.
     *
     * @var list<string>
     */
    private static array $entries = [];

    /**
     * Record a side effect, such as "first.up".
     */
    public static function record(string $entry): void
    {
        self::$entries[] = $entry;
    }

    /**
     * Get the recorded entries in call order.
     *
     * @return list<string>
     */
    public static function entries(): array
    {
        return self::$entries;
    }

    /**
     * Forget every recorded entry.
     */
    public static function reset(): void
    {
        self::$entries = [];
    }
}
