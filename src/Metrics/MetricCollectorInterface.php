<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Extracts metrics from a single Profiler data collector.
 * Implement it (and autoconfigure) to add custom metrics to the audit.
 */
#[AutoconfigureTag(self::TAG)]
interface MetricCollectorInterface
{
    public const TAG = 'profiler_mcp.metric_collector';

    /**
     * Returns null when the profile has no (usable) data for this collector.
     */
    public function collect(Profile $profile): ?CollectorResult;
}
