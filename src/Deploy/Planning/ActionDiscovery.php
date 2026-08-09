<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

use Illuminate\Support\Facades\File;

/**
 * Discover action files within the configured directory.
 */
final class ActionDiscovery
{
    /**
     * Create an action discovery service.
     *
     * @param  string  $actionsPath  The path to search for action files.
     */
    public function __construct(
        private readonly string $actionsPath,
    ) {}

    /**
     * Discover action files in deterministic identity order.
     *
     * @return list<ActionDescriptor>
     */
    public function discover(): array
    {
        if (! File::isDirectory($this->actionsPath)) {
            return [];
        }

        $files = File::files($this->actionsPath);

        $descriptors = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getRealPath();
            $actionId = $file->getBasename('.php');
            $checksum = File::hash($path);

            $descriptors[] = new ActionDescriptor(
                actionId: $actionId,
                path: $path,
                checksum: $checksum
            );
        }

        return collect($descriptors)
            ->sortBy('actionId')
            ->values()
            ->all();
    }
}
