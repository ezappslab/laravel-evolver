<?php

namespace Infinity\Evolver\Deploy\Planning;

use Illuminate\Support\Facades\File;

/**
 * Discovers action files within a specified directory.
 */
class ActionDiscovery
{
    /**
     * Create a new action discovery instance.
     *
     * @param  string  $actionsPath  The path to search for action files.
     */
    public function __construct(
        protected string $actionsPath
    ) {}

    /**
     * Discover all action files in the specified path.
     *
     * @return ActionDescriptor[]
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

        // Sort alphabetically by actionId
        return collect($descriptors)
            ->sortBy('actionId')
            ->values()
            ->all();
    }
}
