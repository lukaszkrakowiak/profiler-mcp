<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\CollectorResult;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\MetricCollectorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;

final readonly class TimeMetricCollector implements MetricCollectorInterface
{
    public function __construct(
        #[Autowire(param: 'profiler_mcp.thresholds.response_time_ms')]
        private int $thresholdMs,
    ) {
    }

    public function collect(Profile $profile): ?CollectorResult
    {
        if (!$profile->hasCollector('time')) {
            return null;
        }

        $time = $profile->getCollector('time');
        if (!$time instanceof TimeDataCollector) {
            return null;
        }

        $durationMs = round($time->getDuration(), 2);

        return new CollectorResult(
            ['total_time_ms' => $durationMs],
            $durationMs > $this->thresholdMs ? [sprintf('Slow response time: %.2f ms', $durationMs)] : [],
        );
    }
}
