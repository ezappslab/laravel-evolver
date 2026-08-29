<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api;

use LogicException;

final class ApiVersionContextStore
{
    private ?ApiVersionContext $context = null;

    public function set(ApiVersionContext $context): void
    {
        $this->context = $context;
    }

    public function get(): ApiVersionContext
    {
        return $this->context ?? throw new LogicException('API version context has not been resolved for this request.');
    }
}
