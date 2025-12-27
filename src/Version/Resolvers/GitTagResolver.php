<?php

namespace Infinity\Evolver\Version\Resolvers;

use Illuminate\Support\Facades\Process;
use Infinity\Evolver\Contracts\VersionResolver;

class GitTagResolver implements VersionResolver
{
    /**
     * Create a new resolver instance.
     */
    public function __construct(
        protected string $stripPrefix = 'v'
    ) {}

    /**
     * Resolve the version from the latest Git tag.
     */
    public function resolve(): ?string
    {
        $tag = $this->getLatestTag();

        if (! $tag) {
            return null;
        }

        if ($this->stripPrefix && str($tag)->startsWith($this->stripPrefix)) {
            return str($tag)->after($this->stripPrefix)->value();
        }

        return $tag;
    }

    /**
     * Get the latest Git tag.
     */
    protected function getLatestTag(): ?string
    {
        $result = Process::run('git describe --tags --abbrev=0');

        if ($result->failed()) {
            return null;
        }

        return str($result->output())->trim()->value() ?: null;
    }
}
