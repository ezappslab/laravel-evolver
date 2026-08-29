<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

use Illuminate\Support\Arr;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Exceptions\VersionResolutionException;
use Infinity\Evolver\Version\SemanticVersion;
use Infinity\Evolver\Version\VersionInterval;
use Infinity\Evolver\Version\VersionManager;

final class Planner
{
    /**
     * Create the authoritative deployment planner.
     */
    public function __construct(
        private readonly ActionDiscovery $discovery,
        private readonly ActionMaterializer $materializer,
        private readonly EvolutionRepository $repository,
        private readonly VersionManager $versions,
        private readonly bool $failOnChangedAction,
    ) {}

    /**
     * Discover and classify every evolution action.
     */
    public function plan(): DeploymentPlan
    {
        $descriptors = $this->discovery->discover();
        $target = $this->versions->target();

        if ($descriptors === []) {
            return new DeploymentPlan([], $target);
        }

        $executed = $this->repository->executed();
        $actions = [];

        foreach ($descriptors as $descriptor) {
            $action = $this->materializer->materialize($descriptor);
            $introducedIn = $action->introducedIn();
            $requiredUntil = $action->requiredUntil();
            $interval = $this->versionInterval($introducedIn, $requiredUntil, $descriptor);

            if (Arr::exists($executed, $descriptor->actionId)) {
                if ($this->failOnChangedAction && $executed[$descriptor->actionId] !== $descriptor->checksum) {
                    throw new ActionChangedException(
                        $descriptor->actionId,
                        $descriptor->path,
                        $executed[$descriptor->actionId],
                        $descriptor->checksum,
                    );
                }

                $status = ActionStatus::Executed;
            } else {
                $status = $this->isApplicable($interval, $target)
                    ? ActionStatus::Pending
                    : ActionStatus::NotApplicable;
            }

            $actions[] = new ActionPlan(
                $descriptor,
                $action,
                $status,
                $introducedIn,
                $requiredUntil,
            );
        }

        return new DeploymentPlan($actions, $target);
    }

    /**
     * Determine whether an unexecuted action applies to the target version.
     */
    private function isApplicable(
        VersionInterval $interval,
        ?SemanticVersion $target,
    ): bool {
        if (! $this->versions->filtersActions()) {
            return true;
        }

        if ($target === null) {
            return false;
        }

        return $interval->contains($target);
    }

    /**
     * Parse and validate an action's version interval.
     */
    private function versionInterval(
        ?string $introducedIn,
        ?string $requiredUntil,
        ActionDescriptor $descriptor,
    ): VersionInterval {
        $introducedVersion = $introducedIn === null
            ? null
            : $this->parseActionVersion($introducedIn, 'introducedIn', $descriptor);
        $requiredUntilVersion = $requiredUntil === null
            ? null
            : $this->parseActionVersion($requiredUntil, 'requiredUntil', $descriptor);

        try {
            return new VersionInterval($introducedVersion, $requiredUntilVersion);
        } catch (VersionResolutionException $exception) {
            throw new VersionResolutionException(
                "Invalid version interval for action [{$descriptor->actionId}] at [{$descriptor->path}]: "
                ."introducedIn [{$introducedIn}] must be less than requiredUntil [{$requiredUntil}].",
                $exception,
            );
        }
    }

    /**
     * Parse action version metadata with its source in any failure message.
     */
    private function parseActionVersion(
        string $version,
        string $field,
        ActionDescriptor $descriptor,
    ): SemanticVersion {
        try {
            return $this->versions->parse($version);
        } catch (VersionResolutionException $exception) {
            throw new VersionResolutionException(
                "Invalid {$field} version [{$version}] for action [{$descriptor->actionId}] at [{$descriptor->path}].",
                $exception,
            );
        }
    }
}
