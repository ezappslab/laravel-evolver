<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version;

enum VersionStrategy: string
{
    /**
     * Disable application version resolution and filtering.
     */
    case None = 'none';

    /**
     * Resolve the application version from a plain-text file.
     */
    case File = 'file';

    /**
     * Resolve the application version from Laravel configuration.
     */
    case Config = 'config';

    /**
     * Resolve the application version from a JSON document.
     */
    case Json = 'json';

    /**
     * Resolve the application version from the latest Git tag.
     */
    case Git = 'git';
}
