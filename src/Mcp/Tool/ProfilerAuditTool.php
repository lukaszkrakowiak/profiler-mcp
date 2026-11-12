<?php

namespace LukaszKrakowiak\ProfilerMcpBundle\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\VarDumper\Cloner\Data;

final readonly class ProfilerAuditTool
{
    public function __construct(
        private Profiler $profiler,
    ) {}

    #[McpTool(
        name: 'profiler_audit_latest',
        description: 'Finds the most recent application HTTP request in Symfony Profiler (skipping assets/WDT), identifies the target controller class/method/file, and returns a complete performance and optimization audit.'
    )]
    public function auditLatest(?string $urlContains = null, ?string $token = null): array
    {
        $targetToken = $token ?? $this->findLatestAppToken($urlContains);
        if (!$targetToken) {
            return ['error' => 'No matching profiler session found. Make a request to the app first.'];
        }

        $profile = $this->profiler->loadProfile($targetToken);
        if (!$profile) {
            return ['error' => sprintf('Could not load profile for token "%s".', $targetToken)];
        }

        $collected = $this->collectMetrics($profile);

        return [
            'token' => $targetToken,
            'profiler_url' => sprintf('http://localhost:8000/_profiler/%s', $targetToken),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'status_code' => $profile->getStatusCode(),
            'controller' => $this->extractControllerInfo($profile),
            'detected_bottlenecks' => $collected['bottlenecks'],
            'metrics' => $collected['metrics'],
        ];
    }

    #[McpTool(
        name: 'profiler_compare',
        description: 'Compares two Symfony Profiler tokens (before and after optimization) and returns deltas for response time, memory, SQL query count, and resolved N+1 patterns.'
    )]
    public function compareProfiles(string $beforeToken, string $afterToken): array
    {
        $beforeProfile = $this->profiler->loadProfile($beforeToken);
        if (!$beforeProfile) {
            return ['error' => sprintf('Could not load "before" profile for token "%s".', $beforeToken)];
        }

        $afterProfile = $this->profiler->loadProfile($afterToken);
        if (!$afterProfile) {
            return ['error' => sprintf('Could not load "after" profile for token "%s".', $afterToken)];
        }

        $before = $this->collectMetrics($beforeProfile)['metrics'];
        $after = $this->collectMetrics($afterProfile)['metrics'];

        $beforeNPlusOne = array_column($before['database']['n_plus_one_candidates'] ?? [], 'sql');
        $afterNPlusOne = array_column($after['database']['n_plus_one_candidates'] ?? [], 'sql');

        return [
            'before' => [
                'token' => $beforeToken,
                'url' => $beforeProfile->getUrl(),
                'status_code' => $beforeProfile->getStatusCode(),
                'controller' => $this->extractControllerInfo($beforeProfile),
            ],
            'after' => [
                'token' => $afterToken,
                'url' => $afterProfile->getUrl(),
                'status_code' => $afterProfile->getStatusCode(),
                'controller' => $this->extractControllerInfo($afterProfile),
            ],
            'diff' => [
                'total_time_ms' => $this->calculateDelta($before['total_time_ms'] ?? null, $after['total_time_ms'] ?? null),
                'peak_memory_mb' => $this->calculateDelta($before['peak_memory_mb'] ?? null, $after['peak_memory_mb'] ?? null),
                'db_query_count' => $this->calculateDelta($before['database']['query_count'] ?? null, $after['database']['query_count'] ?? null),
                'db_time_ms' => $this->calculateDelta($before['database']['total_db_time_ms'] ?? null, $after['database']['total_db_time_ms'] ?? null),
                'twig_time_ms' => $this->calculateDelta($before['twig']['render_time_ms'] ?? null, $after['twig']['render_time_ms'] ?? null),
                'external_http_requests' => $this->calculateDelta($before['external_http_requests'] ?? null, $after['external_http_requests'] ?? null),
            ],
            'n_plus_one_diff' => [
                'resolved_queries' => array_values(array_diff($beforeNPlusOne, $afterNPlusOne)),
                'remaining_queries' => array_values($afterNPlusOne),
            ],
        ];
    }

    private function extractControllerInfo(Profile $profile): array
    {
        if (!$profile->hasCollector('request')) {
            return [];
        }

        /** @var RequestDataCollector $request */
        $request = $profile->getCollector('request');

        $route = $request->getRoute();
        if ($route instanceof Data) {
            $route = $route->getValue(true);
        }

        $controller = $request->getController();
        if ($controller instanceof Data) {
            $controller = $controller->getValue(true);
        }

        $info = [
            'route' => is_string($route) && $route !== '' ? $route : null,
        ];

        if (is_array($controller)) {
            $class = $controller['class'] ?? null;
            $method = $controller['method'] ?? null;

            $info['callable'] = ($class && $method) ? sprintf('%s::%s', $class, $method) : ($class ?? null);
            $info['class'] = $class;
            $info['method'] = $method;
            $info['file'] = $controller['file'] ?? null;
            $info['line'] = $controller['line'] ?? null;
        } elseif (is_string($controller) && $controller !== 'n/a') {
            $info['callable'] = $controller;
        } else {
            $attributes = $request->getRequestAttributes();
            $rawAttributes = $attributes instanceof Data ? $attributes->getValue(true) : $attributes;
            if (is_array($rawAttributes) && isset($rawAttributes['_controller'])) {
                $info['callable'] = is_string($rawAttributes['_controller'])
                    ? $rawAttributes['_controller']
                    : get_debug_type($rawAttributes['_controller']);
            }
        }

        return array_filter($info, static fn (mixed $value): bool => $value !== null);
    }

    private function collectMetrics(Profile $profile): array
    {
        $bottlenecks = [];
        $metrics = [];

        if ($profile->hasCollector('time')) {
            /** @var TimeDataCollector $time */
            $time = $profile->getCollector('time');
            $durationMs = round($time->getDuration(), 2);
            $metrics['total_time_ms'] = $durationMs;

            if ($durationMs > 300) {
                $bottlenecks[] = sprintf('Slow response time: %.2f ms', $durationMs);
            }
        }

        if ($profile->hasCollector('memory')) {
            /** @var MemoryDataCollector $memory */
            $memory = $profile->getCollector('memory');
            $memoryMb = round($memory->getMemory() / 1024 / 1024, 2);
            $metrics['peak_memory_mb'] = $memoryMb;

            if ($memoryMb > 64) {
                $bottlenecks[] = sprintf('High memory usage: %.2f MB', $memoryMb);
            }
        }

        if (class_exists(DoctrineDataCollector::class) && $profile->hasCollector('db')) {
            /** @var DoctrineDataCollector $db */
            $db = $profile->getCollector('db');
            $dbTimeMs = round($db->getTime() * 1000, 2);
            $queryCount = $db->getQueryCount();

            $grouped = [];
            foreach ($db->getQueries() as $queries) {
                foreach ($queries as $q) {
                    $sql = $q['sql'];
                    $grouped[$sql]['sql'] = $sql;
                    $grouped[$sql]['count'] = ($grouped[$sql]['count'] ?? 0) + 1;
                    $grouped[$sql]['total_ms'] = round(($grouped[$sql]['total_ms'] ?? 0) + ($q['executionMS'] ?? 0), 2);
                }
            }

            $nPlusOneCandidates = array_values(array_filter($grouped, static fn (array $item): bool => $item['count'] >= 3));
            usort($nPlusOneCandidates, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

            $metrics['database'] = [
                'query_count' => $queryCount,
                'total_db_time_ms' => $dbTimeMs,
                'n_plus_one_candidates' => $nPlusOneCandidates,
            ];

            if ($nPlusOneCandidates !== []) {
                $bottlenecks[] = sprintf(
                    'Potential N+1 queries detected (%d repeated SQL patterns)',
                    count($nPlusOneCandidates)
                );
            }
        }

        if (class_exists(TwigDataCollector::class) && $profile->hasCollector('twig')) {
            /** @var TwigDataCollector $twig */
            $twig = $profile->getCollector('twig');
            $twigMs = round($twig->getTime() * 1000, 2);
            $metrics['twig'] = [
                'render_time_ms' => $twigMs,
                'template_count' => $twig->getTemplateCount(),
                'templates' => $twig->getTemplates(),
            ];

            if ($twigMs > 100) {
                $bottlenecks[] = sprintf('Slow Twig rendering: %.2f ms across %d templates', $twigMs, $twig->getTemplateCount());
            }
        }

        if (class_exists(HttpClientDataCollector::class) && $profile->hasCollector('http_client')) {
            /** @var HttpClientDataCollector $http */
            $http = $profile->getCollector('http_client');
            $metrics['external_http_requests'] = $http->getRequestCount();
        }

        if (class_exists(CacheDataCollector::class) && $profile->hasCollector('cache')) {
            /** @var CacheDataCollector $cache */
            $cache = $profile->getCollector('cache');
            $totals = $cache->getTotals();
            $metrics['cache'] = [
                'reads' => $totals['reads'] ?? 0,
                'hits' => $totals['hits'] ?? 0,
                'misses' => $totals['misses'] ?? 0,
            ];
        }

        return [
            'bottlenecks' => $bottlenecks,
            'metrics' => $metrics,
        ];
    }

    private function calculateDelta(int|float|null $before, int|float|null $after): ?array
    {
        if ($before === null || $after === null) {
            return null;
        }

        $delta = round($after - $before, 2);
        $percent = $before > 0
            ? round((($after - $before) / $before) * 100, 1)
            : ($after > 0 ? 100.0 : 0.0);

        return [
            'before' => $before,
            'after' => $after,
            'delta' => $delta,
            'change_percent' => $percent,
        ];
    }

    private function findLatestAppToken(?string $urlContains): ?string
    {
        $recent = $this->profiler->find(null, $urlContains, 20, null, null, null);

        foreach ($recent as $entry) {
            $url = $entry['url'] ?? '';
            $method = $entry['method'] ?? 'GET';

            if ($method === 'OPTIONS' || preg_match('#(/(_profiler|_wdt)|favicon\.ico)#', $url)) {
                continue;
            }

            return $entry['token'];
        }

        return null;
    }
}
