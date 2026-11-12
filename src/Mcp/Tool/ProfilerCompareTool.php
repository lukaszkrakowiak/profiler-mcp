<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Mcp\Tool;

use LukaszKrakowiak\ProfilerMcpBundle\Exception\ProfileNotFoundException;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\ProfileComparator;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\ProfileMetricsCollector;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\ControllerInfoExtractor;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\ProfileLoader;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\HttpKernel\Profiler\Profile;

final readonly class ProfilerCompareTool
{
    public function __construct(
        private ProfileLoader $profileLoader,
        private ProfileMetricsCollector $metricsCollector,
        private ProfileComparator $comparator,
        private ControllerInfoExtractor $controllerInfo,
    ) {
    }

    #[McpTool(
        name: 'profiler_compare',
        description: 'Compares two Symfony Profiler tokens (before and after optimization) and returns deltas for response time, memory, SQL query count, and resolved N+1 patterns.'
    )]
    public function compareProfiles(string $beforeToken, string $afterToken): array
    {
        try {
            $before = $this->profileLoader->load($beforeToken, 'before');
            $after = $this->profileLoader->load($afterToken, 'after');
        } catch (ProfileNotFoundException $e) {
            return ['error' => $e->getMessage()];
        }

        return [
            'before' => $this->summarize($beforeToken, $before),
            'after' => $this->summarize($afterToken, $after),
            ...$this->comparator->compare(
                $this->metricsCollector->collect($before)->metrics,
                $this->metricsCollector->collect($after)->metrics,
            ),
        ];
    }

    private function summarize(string $token, Profile $profile): array
    {
        return [
            'token' => $token,
            'url' => $profile->getUrl(),
            'status_code' => $profile->getStatusCode(),
            'controller' => $this->controllerInfo->extract($profile),
        ];
    }
}
