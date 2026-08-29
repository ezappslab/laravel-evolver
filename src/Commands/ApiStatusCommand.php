<?php

declare(strict_types=1);

namespace Infinity\Evolver\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Infinity\Evolver\Api\ApiVersionRegistry;

final class ApiStatusCommand extends Command
{
    /** @var string */
    protected $signature = 'evolver:api-status';

    /** @var string */
    protected $description = 'Display configured API versions and lifecycle states';

    public function handle(ApiVersionRegistry $registry): int
    {
        $definitions = $registry->all();

        if ($definitions === []) {
            $this->components->info('No API versions configured.');

            return self::SUCCESS;
        }

        $now = new DateTimeImmutable;
        $this->components->twoColumnDetail('<fg=gray>API version</>', '<fg=gray>State / Sunset / Successor</>');

        foreach ($definitions as $definition) {
            $this->components->twoColumnDetail(
                $definition->version->value,
                implode(' / ', [
                    $definition->lifecycle->stateAt($now)->value,
                    $definition->lifecycle->sunsetAt?->format(DATE_ATOM) ?? '-',
                    $definition->successor !== null ? $definition->successor->value : '-',
                ]),
            );
        }

        return self::SUCCESS;
    }
}
