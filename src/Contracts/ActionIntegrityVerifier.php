<?php

declare(strict_types=1);

namespace Infinity\Evolver\Contracts;

use Infinity\Evolver\Deploy\Planning\ActionDescriptor;

interface ActionIntegrityVerifier
{
    /**
     * Verify that an action still matches its planned file.
     */
    public function verify(ActionDescriptor $descriptor): void;
}
