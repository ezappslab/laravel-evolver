<?php

namespace Infinity\Evolver\Deploy\Planning;

/**
 * Represents the metadata for a single action.
 */
class ActionDescriptor
{
    /**
     * Create a new action descriptor instance.
     *
     * @param  string  $actionId  The unique identifier for the action.
     * @param  string  $path  The absolute path to the action file.
     * @param  string  $checksum  The MD5 checksum of the action file content.
     */
    public function __construct(
        public readonly string $actionId,
        public readonly string $path,
        public readonly string $checksum
    ) {}
}
