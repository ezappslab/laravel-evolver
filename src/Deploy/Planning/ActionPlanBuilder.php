<?php

namespace Infinity\Evolver\Deploy\Planning;

use Infinity\Evolver\Contracts\ActionRepository;
use Infinity\Evolver\Exceptions\ActionChangedException;
use Infinity\Evolver\Version\VersionManager;

/**
 * Builds the action plan.
 */
class ActionPlanBuilder
{
    /**
     * Create a new action plan builder instance.
     */
    public function __construct(
        protected ActionDiscovery $discovery,
        protected ActionMaterializer $materializer,
        protected ActionRepository $repository,
        protected VersionManager $versionManager,
        protected ApplicabilityPolicy $policy
    ) {}

    /**
     * Build the action plan.
     *
     *
     * @throws ActionChangedException
     */
    public function build(): ActionPlan
    {
        $descriptors = $this->discovery->discover();
        $toRun = [];
        $skipped = [];

        $targetVersion = $this->versionManager->targetRequired();

        // If a target version is required but missing, we should have thrown an exception in VersionManager.
        // If it's not required and missing, we return an empty plan for running, everything else is skipped as out_of_range.
        if ($targetVersion === null) {
            foreach ($descriptors as $descriptor) {
                $skipped[] = [
                    'descriptor' => $descriptor,
                    'status' => ActionStatus::OutOfRange,
                ];
            }

            return new ActionPlan([], $skipped);
        }

        foreach ($descriptors as $descriptor) {
            // Check if it already ran
            $ranChecksum = $this->repository->getSuccessfulRunChecksum($descriptor->actionId);

            if ($ranChecksum !== null) {
                if ($ranChecksum !== $descriptor->checksum) {
                    if (config('evolver.safety.fail_on_changed_action', true)) {
                        throw new ActionChangedException(
                            $descriptor->actionId,
                            $descriptor->path,
                            $ranChecksum,
                            $descriptor->checksum
                        );
                    }

                    $skipped[] = [
                        'descriptor' => $descriptor,
                        'status' => ActionStatus::Changed,
                    ];
                } else {
                    $skipped[] = [
                        'descriptor' => $descriptor,
                        'status' => ActionStatus::AlreadyRan,
                    ];
                }

                continue;
            }

            // Materialize to get metadata
            $action = $this->materializer->materialize($descriptor);
            $metadata = $this->materializer->getMetadata($action);

            $introducedIn = $metadata['introducedIn'] ? $this->versionManager->parse($metadata['introducedIn']) : null;
            $requiredUntil = $metadata['requiredUntil'] ? $this->versionManager->parse($metadata['requiredUntil']) : null;

            if ($this->policy->applies($introducedIn, $requiredUntil, $targetVersion)) {
                $toRun[] = $descriptor;
            } else {
                $skipped[] = [
                    'descriptor' => $descriptor,
                    'status' => ActionStatus::OutOfRange,
                ];
            }
        }

        return new ActionPlan($toRun, $skipped);
    }
}
