<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Tests\Metrics;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\DeltaCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeltaCalculatorTest extends TestCase
{
    public function testImprovementIsReportedAsNegativePercent(): void
    {
        $result = (new DeltaCalculator())->calculate(200, 50);

        self::assertSame(['before' => 200, 'after' => 50, 'delta' => -150.0, 'change_percent' => -75.0], $result);
    }

    public function testRegressionIsReportedAsPositivePercent(): void
    {
        $result = (new DeltaCalculator())->calculate(100, 150);

        self::assertSame(50.0, $result['change_percent']);
        self::assertSame(50.0, $result['delta']);
    }

    public function testGrowthFromZeroIsHundredPercent(): void
    {
        self::assertSame(100.0, (new DeltaCalculator())->calculate(0, 5)['change_percent']);
    }

    public function testZeroToZeroIsNoChange(): void
    {
        self::assertSame(0.0, (new DeltaCalculator())->calculate(0, 0)['change_percent']);
    }

    #[DataProvider('missingValues')]
    public function testMissingMetricYieldsNull(int|float|null $before, int|float|null $after): void
    {
        self::assertNull((new DeltaCalculator())->calculate($before, $after));
    }

    public static function missingValues(): iterable
    {
        yield 'no before' => [null, 10];
        yield 'no after' => [10, null];
        yield 'neither' => [null, null];
    }
}
