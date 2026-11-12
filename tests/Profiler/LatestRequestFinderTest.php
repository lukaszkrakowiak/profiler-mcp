<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Tests\Profiler;

use LukaszKrakowiak\ProfilerMcpBundle\Exception\ProfileNotFoundException;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\LatestRequestFinder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final class LatestRequestFinderTest extends TestCase
{
    public function testSkipsProfilerWdtFaviconAndPreflightNoise(): void
    {
        $finder = new LatestRequestFinder($this->profilerReturning([
            ['token' => 'wdt', 'method' => 'GET', 'url' => 'http://x/_wdt/abc'],
            ['token' => 'prof', 'method' => 'GET', 'url' => 'http://x/_profiler/abc'],
            ['token' => 'fav', 'method' => 'GET', 'url' => 'http://x/favicon.ico'],
            ['token' => 'cors', 'method' => 'OPTIONS', 'url' => 'http://x/api/products'],
            ['token' => 'real', 'method' => 'GET', 'url' => 'http://x/products'],
            ['token' => 'older', 'method' => 'GET', 'url' => 'http://x/orders'],
        ]));

        self::assertSame('real', $finder->findToken());
    }

    public function testThrowsWhenOnlyNoiseIsAvailable(): void
    {
        $finder = new LatestRequestFinder($this->profilerReturning([
            ['token' => 'wdt', 'method' => 'GET', 'url' => 'http://x/_wdt/abc'],
        ]));

        $this->expectException(ProfileNotFoundException::class);
        $finder->findToken();
    }

    private function profilerReturning(array $entries): Profiler
    {
        $profiler = $this->createStub(Profiler::class);
        $profiler->method('find')->willReturn($entries);

        return $profiler;
    }
}
