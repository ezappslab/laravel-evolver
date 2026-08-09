<?php

declare(strict_types=1);

namespace Infinity\Evolver\Exceptions;

final class EvolutionTableMissingException extends EvolverException
{
    /**
     * Create an exception for a missing Evolution table.
     */
    public function __construct()
    {
        parent::__construct('Evolution table not found. Run your Laravel migrations before using Evolver.');
    }
}
