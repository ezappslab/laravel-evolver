<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Support\Facades\Process;
use Infinity\Evolver\Contracts\VersionResolver;

final class GitTagResolver implements VersionResolver
{
    /**
     * Create a Git tag resolver.
     */
    public function __construct(
        private readonly string $stripPrefix = 'v',
    ) {}

    /**
     * Resolve the version from the latest Git tag.
     */
    public function resolve(): ?string
    {
        $tag = $this->getLatestTag();

        if (blank($tag)) {
            return null;
        }

        if (filled($this->stripPrefix) && str($tag)->startsWith($this->stripPrefix)) {
            return str($tag)->after($this->stripPrefix)->value();
        }

        return $tag;
    }

    /**
     * Get the latest Git tag from the current repository.
     */
    private function getLatestTag(): ?string
    {
        $result = Process::run('git describe --tags --abbrev=0');

        if ($result->failed()) {
            return null;
        }

        $tag = str($result->output())->trim()->value();

        return filled($tag) ? $tag : null;
    }
}
