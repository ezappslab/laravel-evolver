<?php

declare(strict_types=1);

namespace Infinity\Evolver\Versioning\Resolvers;

use Infinity\Evolver\Contracts\ApplicationVersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Symfony\Component\Process\Process;

/** Resolves the latest tag reachable from HEAD without invoking a shell. */
final class GitTagResolver implements ApplicationVersionResolver
{
    /**
     * Create a new Git tag version resolver instance.
     *
     * Prefixes are checked in configured order and the first match is removed.
     *
     * @param  list<string>  $prefixes
     */
    public function __construct(private readonly string $path, private readonly array $prefixes = ['v', 'V']) {}

    /**
     * Resolve the application version from the latest tag reachable from HEAD.
     *
     *
     * @throws VersionResolutionException
     */
    public function resolve(): ?string
    {
        if (! is_dir($this->path.'/.git')) {
            throw new VersionResolutionException('Git metadata is unavailable.');
        }

        $process = new Process(['git', 'describe', '--tags', '--abbrev=0'], $this->path);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new VersionResolutionException('No reachable Git tag could be resolved.');
        }

        $tag = trim($process->getOutput());
        foreach ($this->prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($tag, $prefix)) {
                return substr($tag, strlen($prefix));
            }
        }

        return $tag === '' ? null : $tag;
    }
}
