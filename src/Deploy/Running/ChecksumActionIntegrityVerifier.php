<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Running;

use Illuminate\Support\Facades\File;
use Infinity\Evolver\Contracts\ActionIntegrityVerifier;
use Infinity\Evolver\Deploy\Planning\ActionDescriptor;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\InvalidActionException;

final class ChecksumActionIntegrityVerifier implements ActionIntegrityVerifier
{
    /**
     * Verify that an action file is present and unchanged since planning.
     */
    public function verify(ActionDescriptor $descriptor): void
    {
        if (! File::exists($descriptor->path)) {
            throw new InvalidActionException(
                $descriptor->path,
                "Action file not found before execution: {$descriptor->path}",
            );
        }

        $checksum = File::hash($descriptor->path, 'sha256');

        if ($checksum !== $descriptor->checksum) {
            throw new ActionChangedException(
                $descriptor->actionId,
                $descriptor->path,
                $descriptor->checksum,
                $checksum,
            );
        }
    }
}
