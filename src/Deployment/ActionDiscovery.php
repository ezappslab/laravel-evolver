<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Infinity\Evolver\Contracts\ActionSource;
use Infinity\Evolver\Exceptions\InvalidActionException;

/** Locates and fingerprints action files, loading them only on demand and never invoking their deployment methods. */
final class ActionDiscovery implements ActionSource
{
    /**
     * Create a new action discovery instance.
     */
    public function __construct(private readonly Filesystem $files, private readonly string $path) {}

    /**
     * Discover PHP actions in deterministic, case-sensitive filename order.
     *
     * Files are hashed here but loaded only when their action is first used.
     * Case-insensitive identity comparison prevents deployments from changing
     * behavior when moved between case-sensitive and insensitive filesystems.
     * When identifiers are given, every file is still checked for duplicate
     * identities but only the matching files are hashed and described.
     *
     * @param  list<string>|null  $only
     * @return list<ActionDescriptor>
     *
     * @throws InvalidActionException
     */
    public function discover(?array $only = null): array
    {
        if (! $this->files->isDirectory($this->path)) {
            return [];
        }
        $result = [];
        $identities = [];
        $wanted = $only === null ? null : collect($only)->flip()->all();
        foreach ($this->files->files($this->path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $id = $file->getBasename('.php');
            $normalized = Str::lower($id);
            if (isset($identities[$normalized])) {
                throw new InvalidActionException("Duplicate action identity [{$id}].");
            }
            $identities[$normalized] = true;
            if ($wanted !== null && ! isset($wanted[$id])) {
                continue;
            }
            $path = $file->getPathname();
            $result[] = new ActionDescriptor($id, $path, $this->hash($id, $path), fn (): Action => $this->load($id, $path));
        }
        $result = collect(Arr::sort($result, static fn (ActionDescriptor $action): string => $action->id))->values()->all();

        return $result;
    }

    /**
     * Re-hash the action's file, used to detect edits made after planning.
     *
     *
     * @throws InvalidActionException
     */
    public function checksum(ActionDescriptor $action): string
    {
        return $this->hash($action->id, $action->path ?? throw new InvalidActionException("Action [{$action->id}] has no source file."));
    }

    /**
     * Compute the SHA-256 checksum of the given action file.
     *
     * Broken links, deleted files, and unreadable files are all reported as
     * invalid actions instead of surfacing as raw filesystem warnings.
     *
     *
     * @throws InvalidActionException
     */
    private function hash(string $id, string $path): string
    {
        try {
            $checksum = $this->files->hash($path, 'sha256');
        } catch (\Throwable $e) {
            throw new InvalidActionException("Unable to read action [{$id}].", 0, $e);
        }

        return is_string($checksum) ? $checksum : throw new InvalidActionException("Unable to read action [{$id}].");
    }

    /**
     * Load the action returned by the given file.
     *
     *
     * @throws InvalidActionException
     */
    private function load(string $id, string $path): Action
    {
        try {
            $action = $this->files->getRequire($path);
        } catch (\Throwable $e) {
            throw new InvalidActionException("Unable to load action [{$id}].", 0, $e);
        }
        if (! $action instanceof Action) {
            throw new InvalidActionException("Action [{$id}] must return an instance of ".Action::class.'.');
        }

        return $action;
    }
}
