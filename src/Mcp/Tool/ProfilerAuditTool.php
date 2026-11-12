<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Mcp\Tool;

use LukaszKrakowiak\ProfilerMcpBundle\Exception\ProfileNotFoundException;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\ProfileMetricsCollector;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\ControllerInfoExtractor;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\LatestRequestFinder;
use LukaszKrakowiak\ProfilerMcpBundle\Profiler\ProfileLoader;
use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ProfilerAuditTool
{
    public function __construct(
        private ProfileLoader $profileLoader,
        private LatestRequestFinder $latestRequestFinder,
        private ProfileMetricsCollector $metricsCollector,
        private ControllerInfoExtractor $controllerInfo,
        #[Autowire(param: 'profiler_mcp.profiler_base_url')]
        private string $profilerBaseUrl,
    ) {
    }

    #[McpTool(
        name: 'profiler_audit_latest',
        description: 'Finds the most recent application HTTP request in Symfony Profiler (skipping assets/WDT), identifies the target controller class/method/file, and returns a complete performance and optimization audit.'
    )]
    public function auditLatest(?string $urlContains = null, ?string $token = null): array
    {
        try {
            $token ??= $this->latestRequestFinder->findToken($urlContains);
            $profile = $this->profileLoader->load($token);
        } catch (ProfileNotFoundException $e) {
            return ['error' => $e->getMessage()];
        }

        $result = $this->metricsCollector->collect($profile);

        return [
            'token' => $token,
            'profiler_url' => sprintf('%s/_profiler/%s', rtrim($this->profilerBaseUrl, '/'), $token),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'status_code' => $profile->getStatusCode(),
            'controller' => $this->controllerInfo->extract($profile),
            'detected_bottlenecks' => $result->bottlenecks,
            'metrics' => $result->metrics,
        ];
    }
}
