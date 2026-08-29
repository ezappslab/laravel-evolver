<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api;

final readonly class ApiVersionDefinition
{
    public function __construct(
        public ApiVersion $version,
        public ApiVersionLifecycle $lifecycle,
        public ?ApiVersion $successor = null,
        public ?string $successorUrl = null,
    ) {
        if ($this->successor?->equals($this->version)) {
            throw new \InvalidArgumentException('An API version cannot be its own successor.');
        }
    }
}
