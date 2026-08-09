<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

/**
 * Describe a discovered action file.
 */
final class ActionDescriptor
{
    /**
     * Create an action descriptor.
     *
     * @param  string  $actionId  The unique identifier for the action.
     * @param  string  $path  The absolute path to the action file.
     * @param  string  $checksum  The checksum of the action file contents.
     */
    public function __construct(
        public readonly string $actionId,
        public readonly string $path,
        public readonly string $checksum,
    ) {}
}
