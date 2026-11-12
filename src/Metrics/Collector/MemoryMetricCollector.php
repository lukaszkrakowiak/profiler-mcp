<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\CollectorResult;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\MetricCollectorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;

final readonly class MemoryMetricCollector implements MetricCollectorInterface
{
    public function __construct(
        #[Autowire(param: 'profiler_mcp.thresholds.memory_mb')]
        private int $thresholdMb,
    ) {
    }

    public function collect(Profile $profile): ?CollectorResult
    {
        if (!$profile->hasCollector('memory')) {
            return null;
        }

        $memory = $profile->getCollector('memory');
        if (!$memory instanceof MemoryDataCollector) {
            return null;
        }

        $memoryMb = round($memory->getMemory() / 1024 / 1024, 2);

        return new CollectorResult(
            ['peak_memory_mb' => $memoryMb],
            $memoryMb > $this->thresholdMb ? [sprintf('High memory usage: %.2f MB', $memoryMb)] : [],
        );
    }
}
