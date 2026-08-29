<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api;

final readonly class ApiVersionContext
{
    public function __construct(
        public ApiVersionDefinition $definition,
        public ApiVersionState $state,
    ) {}

    public function version(): ApiVersion
    {
        return $this->definition->version;
    }
}
