<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics;

final readonly class CollectorResult
{
    /**
     * @param array<string, mixed> $metrics     fragment merged into the final metrics map
     * @param list<string>         $bottlenecks human-readable findings
     */
    public function __construct(
        public array $metrics,
        public array $bottlenecks = [],
    ) {
    }
}
