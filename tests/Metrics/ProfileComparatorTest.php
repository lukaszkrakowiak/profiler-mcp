<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Tests\Metrics;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\DeltaCalculator;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\ProfileComparator;
use PHPUnit\Framework\TestCase;

final class ProfileComparatorTest extends TestCase
{
    private ProfileComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new ProfileComparator(new DeltaCalculator());
    }

    public function testOptimizationShowsFasterResponseAndFewerQueries(): void
    {
        $before = [
            'total_time_ms' => 800.0,
            'database' => ['query_count' => 51, 'total_db_time_ms' => 600.0, 'n_plus_one_candidates' => []],
        ];
        $after = [
            'total_time_ms' => 200.0,
            'database' => ['query_count' => 2, 'total_db_time_ms' => 20.0, 'n_plus_one_candidates' => []],
        ];

        $diff = $this->comparator->compare($before, $after)['diff'];

        self::assertSame(-75.0, $diff['total_time_ms']['change_percent']);
        self::assertSame(-96.1, $diff['db_query_count']['change_percent']);
        self::assertSame(-49.0, $diff['db_query_count']['delta']);
    }

    public function testMetricMissingOnOneSideIsNotCompared(): void
    {
        $diff = $this->comparator->compare(['total_time_ms' => 100.0], ['peak_memory_mb' => 12.0])['diff'];

        self::assertNull($diff['total_time_ms']);
        self::assertNull($diff['peak_memory_mb']);
        self::assertNull($diff['db_query_count']);
    }

    public function testResolvedAndRemainingNPlusOnePatterns(): void
    {
        $before = ['database' => ['n_plus_one_candidates' => [
            ['sql' => 'SELECT * FROM author WHERE id = ?'],
            ['sql' => 'SELECT * FROM tag WHERE post_id = ?'],
        ]]];
        $after = ['database' => ['n_plus_one_candidates' => [
            ['sql' => 'SELECT * FROM tag WHERE post_id = ?'],
        ]]];

        $nPlusOne = $this->comparator->compare($before, $after)['n_plus_one_diff'];

        self::assertSame(['SELECT * FROM author WHERE id = ?'], $nPlusOne['resolved_queries']);
        self::assertSame(['SELECT * FROM tag WHERE post_id = ?'], $nPlusOne['remaining_queries']);
    }
}
