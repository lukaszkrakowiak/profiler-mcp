<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Tests\Mcp;

use LukaszKrakowiak\ProfilerMcpBundle\Mcp\Tool\ProfilerAuditTool;
use LukaszKrakowiak\ProfilerMcpBundle\Mcp\Tool\ProfilerCompareTool;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\DeltaCalculator;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\ProfileComparator;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\ProfileMetricsCollector;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\ControllerInfoExtractor;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\LatestRequestFinder;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\ProfileLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final class ProfilerToolsTest extends TestCase
{
    public function testAuditLatestReturnsErrorWhenNoRequestWasProfiled(): void
    {
        $profiler = $this->createStub(Profiler::class);
        $profiler->method('find')->willReturn([]);

        $result = $this->auditTool($profiler)->auditLatest();

        self::assertArrayHasKey('error', $result);
        self::assertArrayNotHasKey('metrics', $result);
    }

    public function testAuditByTokenBuildsProfilerLinkAndRequestSummary(): void
    {
        $profile = new Profile('tok123');
        $profile->setMethod('GET');
        $profile->setUrl('http://localhost/products');
        $profile->setStatusCode(200);

        $profiler = $this->createStub(Profiler::class);
        $profiler->method('loadProfile')->willReturn($profile);

        $result = $this->auditTool($profiler)->auditLatest(token: 'tok123');

        self::assertSame('http://localhost:8000/_profiler/tok123', $result['profiler_url']);
        self::assertSame('GET', $result['method']);
        self::assertSame(200, $result['status_code']);
        self::assertSame([], $result['detected_bottlenecks']);
    }

    public function testCompareReportsWhichProfileIsMissing(): void
    {
        $profiler = $this->createStub(Profiler::class);
        $profiler->method('loadProfile')->willReturnCallback(
            static fn (string $token): ?Profile => 'good' === $token ? new Profile('good') : null,
        );

        $result = $this->compareTool($profiler)->compareProfiles('good', 'gone');

        self::assertStringContainsString('"after"', $result['error']);
        self::assertStringContainsString('gone', $result['error']);
    }

    public function testCompareReturnsBothSummariesAndDiff(): void
    {
        $profiler = $this->createStub(Profiler::class);
        $profiler->method('loadProfile')->willReturnCallback(static function (string $token): Profile {
            $profile = new Profile($token);
            $profile->setUrl('http://localhost/products');
            $profile->setStatusCode(200);

            return $profile;
        });

        $result = $this->compareTool($profiler)->compareProfiles('b', 'a');

        self::assertSame('b', $result['before']['token']);
        self::assertSame('a', $result['after']['token']);
        self::assertArrayHasKey('diff', $result);
        self::assertArrayHasKey('n_plus_one_diff', $result);
    }

    private function auditTool(Profiler $profiler): ProfilerAuditTool
    {
        return new ProfilerAuditTool(
            new ProfileLoader($profiler),
            new LatestRequestFinder($profiler),
            new ProfileMetricsCollector([]),
            new ControllerInfoExtractor(),
            'http://localhost:8000/',
        );
    }

    private function compareTool(Profiler $profiler): ProfilerCompareTool
    {
        return new ProfilerCompareTool(
            new ProfileLoader($profiler),
            new ProfileMetricsCollector([]),
            new ProfileComparator(new DeltaCalculator()),
            new ControllerInfoExtractor(),
        );
    }
}
