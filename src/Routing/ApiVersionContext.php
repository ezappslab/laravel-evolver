<?php

declare(strict_types=1);

namespace Infinity\Evolver\Routing;

/** Holds the selected API version for the lifetime of the current request. */
final class ApiVersionContext
{
    private ?string $version = null;

    /**
     * Store the API version selected before route matching.
     */
    public function set(?string $version): void
    {
        $this->version = $version;
    }

    /**
     * Retrieve the selected API version for downstream application services.
     */
    public function get(): ?string
    {
        return $this->version;
    }
}
