<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\CollectorResult;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\MetricCollectorInterface;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Profiler\Profile;

final readonly class DoctrineMetricCollector implements MetricCollectorInterface
{
    public function __construct(
        #[Autowire(param: 'profiler_mcp.thresholds.n_plus_one_repeats')]
        private int $minRepeats,
    ) {
    }

    public function collect(Profile $profile): ?CollectorResult
    {
        if (!$profile->hasCollector('db')) {
            return null;
        }

        $db = $profile->getCollector('db');
        if (!$db instanceof DoctrineDataCollector) {
            return null;
        }

        $candidates = $this->findRepeatedQueries($db);

        return new CollectorResult(
            ['database' => [
                'query_count' => $db->getQueryCount(),
                'total_db_time_ms' => round($db->getTime() * 1000, 2),
                'n_plus_one_candidates' => $candidates,
            ]],
            [] === $candidates ? [] : [sprintf('Potential N+1 queries detected (%d repeated SQL patterns)', \count($candidates))],
        );
    }

    /**
     * @return list<array{sql: string, count: int, total_ms: float}>
     */
    private function findRepeatedQueries(DoctrineDataCollector $db): array
    {
        $grouped = [];
        foreach ($db->getQueries() as $connectionQueries) {
            foreach ($connectionQueries as $query) {
                $sql = $query['sql'];
                $grouped[$sql] ??= ['sql' => $sql, 'count' => 0, 'total_ms' => 0.0];
                ++$grouped[$sql]['count'];
                $grouped[$sql]['total_ms'] = round($grouped[$sql]['total_ms'] + ($query['executionMS'] ?? 0), 2);
            }
        }

        $repeated = array_values(array_filter($grouped, fn (array $item): bool => $item['count'] >= $this->minRepeats));
        usort($repeated, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $repeated;
    }
}
