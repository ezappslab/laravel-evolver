<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** HTTP-safe failure for invalid or unsupported public API versions. */
final class UnsupportedApiVersionException extends HttpException
{
    /**
     * Create a new unsupported API version exception instance.
     *
     * The exception renders as a 404 response.
     */
    public function __construct(string $message)
    {
        parent::__construct(404, $message);
    }
}
