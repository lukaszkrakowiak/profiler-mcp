<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Tests\Metrics;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector\DoctrineMetricCollector;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector\MemoryMetricCollector;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector\TimeMetricCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class CollectorsTest extends TestCase
{
    public function testSlowResponseIsFlaggedAsBottleneck(): void
    {
        $time = $this->createStub(TimeDataCollector::class);
        $time->method('getDuration')->willReturn(750.456);

        $result = (new TimeMetricCollector(500))->collect($this->profileWith('time', $time));

        self::assertSame(['total_time_ms' => 750.46], $result->metrics);
        self::assertCount(1, $result->bottlenecks);
        self::assertStringContainsString('Slow response time', $result->bottlenecks[0]);
    }

    public function testFastResponseHasNoBottleneck(): void
    {
        $time = $this->createStub(TimeDataCollector::class);
        $time->method('getDuration')->willReturn(120.0);

        $result = (new TimeMetricCollector(500))->collect($this->profileWith('time', $time));

        self::assertSame([], $result->bottlenecks);
    }

    public function testHighMemoryIsFlaggedAndConvertedToMegabytes(): void
    {
        $memory = $this->createStub(MemoryDataCollector::class);
        $memory->method('getMemory')->willReturn(64 * 1024 * 1024);

        $result = (new MemoryMetricCollector(32))->collect($this->profileWith('memory', $memory));

        self::assertSame(['peak_memory_mb' => 64.0], $result->metrics);
        self::assertCount(1, $result->bottlenecks);
    }

    public function testCollectorsSkipProfilesWithoutTheirData(): void
    {
        $empty = new Profile('abc');

        self::assertNull((new TimeMetricCollector(500))->collect($empty));
        self::assertNull((new MemoryMetricCollector(32))->collect($empty));
        self::assertNull((new DoctrineMetricCollector(3))->collect($empty));
    }

    public function testRepeatedQueriesAreReportedAsNPlusOneSortedByCount(): void
    {
        $db = $this->createStub(DoctrineDataCollector::class);
        $db->method('getQueryCount')->willReturn(10);
        $db->method('getTime')->willReturn(0.05);
        $db->method('getQueries')->willReturn(['default' => [
            ['sql' => 'SELECT * FROM tag WHERE post_id = ?', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM author WHERE id = ?', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM author WHERE id = ?', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM author WHERE id = ?', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM author WHERE id = ?', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM author WHERE id = ?', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM tag WHERE post_id = ?', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM tag WHERE post_id = ?', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM post', 'executionMS' => 0.01],
            ['sql' => 'SELECT * FROM tag WHERE post_id = ?', 'executionMS' => 0.01],
        ]]);

        $result = (new DoctrineMetricCollector(3))->collect($this->profileWith('db', $db));

        self::assertSame(10, $result->metrics['database']['query_count']);
        self::assertSame(50.0, $result->metrics['database']['total_db_time_ms']);

        $candidates = $result->metrics['database']['n_plus_one_candidates'];
        self::assertSame(['SELECT * FROM author WHERE id = ?', 'SELECT * FROM tag WHERE post_id = ?'], array_column($candidates, 'sql'));
        self::assertSame([5, 4], array_column($candidates, 'count'));
        self::assertStringContainsString('2 repeated', $result->bottlenecks[0]);
    }

    public function testQueriesBelowRepeatThresholdAreNotNPlusOne(): void
    {
        $db = $this->createStub(DoctrineDataCollector::class);
        $db->method('getQueries')->willReturn(['default' => [
            ['sql' => 'SELECT 1'],
            ['sql' => 'SELECT 1'],
        ]]);

        $result = (new DoctrineMetricCollector(3))->collect($this->profileWith('db', $db));

        self::assertSame([], $result->metrics['database']['n_plus_one_candidates']);
        self::assertSame([], $result->bottlenecks);
    }

    private function profileWith(string $name, object $collector): Profile
    {
        $profile = new Profile('abc');
        $profile->addCollector($collector instanceof \Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface ? $this->named($name, $collector) : $collector);

        return $profile;
    }

    private function named(string $name, object $collector): object
    {
        $collector->method('getName')->willReturn($name);

        return $collector;
    }
}
