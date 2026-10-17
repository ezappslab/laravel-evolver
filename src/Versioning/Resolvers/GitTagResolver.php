<?php

declare(strict_types=1);

namespace Infinity\Evolver\Versioning\Resolvers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\Factory;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\ApplicationVersionResolver;
use Infinity\Evolver\Exceptions\VersionResolutionException;

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
    public function __construct(private readonly Filesystem $files, private readonly Factory $processes, private readonly string $path, private readonly array $prefixes = ['v', 'V']) {}

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
        if (! $this->files->isDirectory($this->path)) {
            throw new VersionResolutionException('Git metadata is unavailable.');
        }

        $repository = $this->processes->newPendingProcess()->path($this->path)->run(['git', 'rev-parse', '--is-inside-work-tree']);
        if (! $repository->successful() || Str::trim($repository->output()) !== 'true') {
            throw new VersionResolutionException('Git metadata is unavailable.');
        }

        $process = $this->processes->newPendingProcess()->path($this->path)->run(['git', 'describe', '--tags', '--abbrev=0']);
        if (! $process->successful()) {
            throw new VersionResolutionException('No reachable Git tag could be resolved.');
        }

        $tag = Str::trim($process->output());
        foreach ($this->prefixes as $prefix) {
            if ($prefix !== '' && Str::startsWith($tag, $prefix)) {
                $tag = Str::substr($tag, Str::length($prefix));

                break;
            }
        }

        return $tag === '' ? null : $tag;
    }
}
