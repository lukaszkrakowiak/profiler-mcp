<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Tests\Metrics;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\CollectorResult;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\MetricCollectorInterface;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\ProfileMetricsCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class ProfileMetricsCollectorTest extends TestCase
{
    public function testMergesMetricsAndBottlenecksFromAllCollectorsSkippingEmptyOnes(): void
    {
        $collector = new ProfileMetricsCollector([
            $this->collectorReturning(new CollectorResult(['total_time_ms' => 900.0], ['Slow response time'])),
            $this->collectorReturning(null),
            $this->collectorReturning(new CollectorResult(['peak_memory_mb' => 80.0], ['High memory usage'])),
        ]);

        $result = $collector->collect(new Profile('abc'));

        self::assertSame(['total_time_ms' => 900.0, 'peak_memory_mb' => 80.0], $result->metrics);
        self::assertSame(['Slow response time', 'High memory usage'], $result->bottlenecks);
    }

    public function testNoCollectorsMeansEmptyAudit(): void
    {
        $result = (new ProfileMetricsCollector([]))->collect(new Profile('abc'));

        self::assertSame([], $result->metrics);
        self::assertSame([], $result->bottlenecks);
    }

    private function collectorReturning(?CollectorResult $result): MetricCollectorInterface
    {
        return new class($result) implements MetricCollectorInterface {
            public function __construct(private readonly ?CollectorResult $result)
            {
            }

            public function collect(Profile $profile): ?CollectorResult
            {
                return $this->result;
            }
        };
    }
}
