<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics;

final readonly class ProfileMetrics
{
    /**
     * @param array<string, mixed> $metrics
     * @param list<string>         $bottlenecks
     */
    public function __construct(
        public array $metrics,
        public array $bottlenecks,
    ) {
    }
}
