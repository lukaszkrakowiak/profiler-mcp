<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\CollectorResult;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\MetricCollectorInterface;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;

final readonly class HttpClientMetricCollector implements MetricCollectorInterface
{
    public function collect(Profile $profile): ?CollectorResult
    {
        if (!$profile->hasCollector('http_client')) {
            return null;
        }

        $http = $profile->getCollector('http_client');
        if (!$http instanceof HttpClientDataCollector) {
            return null;
        }

        return new CollectorResult(['external_http_requests' => $http->getRequestCount()]);
    }
}
