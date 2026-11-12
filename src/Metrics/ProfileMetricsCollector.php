<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpKernel\Profiler\Profile;

final readonly class ProfileMetricsCollector
{
    /**
     * @param iterable<MetricCollectorInterface> $collectors
     */
    public function __construct(
        #[AutowireIterator(MetricCollectorInterface::TAG)]
        private iterable $collectors,
    ) {
    }

    public function collect(Profile $profile): ProfileMetrics
    {
        $metrics = [];
        $bottlenecks = [];

        foreach ($this->collectors as $collector) {
            $result = $collector->collect($profile);
            if (null === $result) {
                continue;
            }

            $metrics = array_merge($metrics, $result->metrics);
            array_push($bottlenecks, ...$result->bottlenecks);
        }

        return new ProfileMetrics($metrics, $bottlenecks);
    }
}
