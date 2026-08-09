<?php

declare(strict_types=1);

namespace Infinity\Evolver\Deploy\Planning;

use Illuminate\Support\Arr;
use Infinity\Evolver\Contracts\EvolutionRepository;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Version\SemanticVersion;
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
        $target = $this->versions->target();
        $executed = $this->repository->executed();
        $actions = [];

        foreach ($this->discovery->discover() as $descriptor) {
            $action = $this->materializer->materialize($descriptor);
            $introducedIn = $action->introducedIn();
            $requiredUntil = $action->requiredUntil();

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
                $status = $this->isApplicable($introducedIn, $requiredUntil, $target)
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
    private function isApplicable(?string $introducedIn, ?string $requiredUntil, ?SemanticVersion $target): bool
    {
        if (! $this->versions->filtersActions()) {
            return true;
        }

        if ($target === null) {
            return false;
        }

        if ($introducedIn !== null && $target->isLessThan($this->versions->parse($introducedIn))) {
            return false;
        }

        return $requiredUntil === null || $target->isLessThan($this->versions->parse($requiredUntil));
    }
}
