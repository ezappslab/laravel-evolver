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
     * Git itself locates the repository, so worktrees, submodules, and
     * applications nested inside a larger repository are all supported.
     *
     *
     * @throws VersionResolutionException
     */
    public function resolve(): ?string
    {
        if (! is_dir($this->path)) {
            throw new VersionResolutionException('Git metadata is unavailable.');
        }

        $repository = new Process(['git', 'rev-parse', '--is-inside-work-tree'], $this->path);
        $repository->run();
        if (! $repository->isSuccessful() || trim($repository->getOutput()) !== 'true') {
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
                $tag = substr($tag, strlen($prefix));

                break;
            }
        }

        return $tag === '' ? null : $tag;
    }
}
