<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

/** Immutable discovered action metadata. */
final readonly class ActionDescriptor
{
    /**
     * Create a new action descriptor instance.
     */
    public function __construct(public string $id, public string $path, public string $checksum, public Action $action) {}
}
