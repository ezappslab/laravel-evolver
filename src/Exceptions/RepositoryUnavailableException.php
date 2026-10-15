<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

/** Raised when package migrations have not created the history table. */
class RepositoryUnavailableException extends EvolverException {}
