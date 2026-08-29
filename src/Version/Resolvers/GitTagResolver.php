<?php

declare(strict_types=1);

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Support\Facades\Process;
use Infinity\Evolver\Contracts\VersionResolver;
use Infinity\Evolver\Exceptions\VersionResolverException;

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
        $tags = Process::path(base_path())->run('git tag --merged HEAD');

        if ($tags->failed()) {
            throw new VersionResolverException(
                'Unable to inspect Git tags: '.str($tags->errorOutput())->trim()->value(),
            );
        }

        if (blank($tags->output())) {
            return null;
        }

        $result = Process::path(base_path())->run('git describe --tags --abbrev=0');

        if ($result->failed()) {
            throw new VersionResolverException(
                'Unable to resolve the latest Git tag: '.str($result->errorOutput())->trim()->value(),
            );
        }

        $tag = str($result->output())->trim()->value();

        return filled($tag) ? $tag : null;
    }
}
