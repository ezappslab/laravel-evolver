<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

/** Raised when persisted and current action checksums differ. */
class ActionChangedException extends EvolverException {}
