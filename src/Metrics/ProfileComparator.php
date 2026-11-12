<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics;

final readonly class ProfileComparator
{
    public function __construct(private DeltaCalculator $deltas)
    {
    }

    /**
     * @param array<string, mixed> $before metrics of the "before" profile
     * @param array<string, mixed> $after  metrics of the "after" profile
     *
     * @return array{diff: array<string, mixed>, n_plus_one_diff: array{resolved_queries: list<string>, remaining_queries: list<string>}}
     */
    public function compare(array $before, array $after): array
    {
        $diff = [];
        foreach (self::COMPARED_METRICS as $name => $path) {
            $diff[$name] = $this->deltas->calculate($this->value($before, $path), $this->value($after, $path));
        }

        $beforeSql = $this->nPlusOneSql($before);
        $afterSql = $this->nPlusOneSql($after);

        return [
            'diff' => $diff,
            'n_plus_one_diff' => [
                'resolved_queries' => array_values(array_diff($beforeSql, $afterSql)),
                'remaining_queries' => array_values($afterSql),
            ],
        ];
    }

    private const COMPARED_METRICS = [
        'total_time_ms' => ['total_time_ms'],
        'peak_memory_mb' => ['peak_memory_mb'],
        'db_query_count' => ['database', 'query_count'],
        'db_time_ms' => ['database', 'total_db_time_ms'],
        'twig_time_ms' => ['twig', 'render_time_ms'],
        'external_http_requests' => ['external_http_requests'],
    ];

    /**
     * @param list<string> $path
     */
    private function value(array $metrics, array $path): int|float|null
    {
        foreach ($path as $segment) {
            if (!\is_array($metrics) || !isset($metrics[$segment])) {
                return null;
            }
            $metrics = $metrics[$segment];
        }

        return \is_int($metrics) || \is_float($metrics) ? $metrics : null;
    }

    /**
     * @return list<string>
     */
    private function nPlusOneSql(array $metrics): array
    {
        return array_column($metrics['database']['n_plus_one_candidates'] ?? [], 'sql');
    }
}
