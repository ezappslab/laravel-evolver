<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** HTTP-safe failure for header-versioned API requests without a version. */
final class MissingApiVersionException extends HttpException
{
    /**
     * Create a new missing API version exception instance.
     *
     * The exception renders as a 400 response.
     */
    public function __construct(string $message)
    {
        parent::__construct(400, $message);
    }
}
