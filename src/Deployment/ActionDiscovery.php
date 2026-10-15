<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deployment;

use Illuminate\Filesystem\Filesystem;
use Infinity\Evolver\Exceptions\InvalidActionException;

/** Locates and fingerprints action files, loading them only on demand and never invoking their deployment methods. */
final class ActionDiscovery
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
        $wanted = $only === null ? null : array_fill_keys($only, true);
        foreach ($this->files->files($this->path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $id = $file->getBasename('.php');
            $normalized = strtolower($id);
            if (isset($identities[$normalized])) {
                throw new InvalidActionException("Duplicate action identity [{$id}].");
            }
            $identities[$normalized] = true;
            if ($wanted !== null && ! isset($wanted[$id])) {
                continue;
            }
            $path = $file->getRealPath();
            $result[] = new ActionDescriptor($id, $path, hash_file('sha256', $path), fn (): Action => $this->load($id, $path));
        }
        usort($result, static fn (ActionDescriptor $a, ActionDescriptor $b): int => $a->id <=> $b->id);

        return $result;
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
