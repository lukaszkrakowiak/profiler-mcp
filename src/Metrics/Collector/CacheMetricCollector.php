<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\CollectorResult;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\MetricCollectorInterface;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;

final readonly class CacheMetricCollector implements MetricCollectorInterface
{
    public function collect(Profile $profile): ?CollectorResult
    {
        if (!$profile->hasCollector('cache')) {
            return null;
        }

        $cache = $profile->getCollector('cache');
        if (!$cache instanceof CacheDataCollector) {
            return null;
        }

        $totals = $cache->getTotals();

        return new CollectorResult(['cache' => [
            'reads' => $totals['reads'] ?? 0,
            'hits' => $totals['hits'] ?? 0,
            'misses' => $totals['misses'] ?? 0,
        ]]);
    }
}
