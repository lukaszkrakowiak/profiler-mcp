<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics;

final class DeltaCalculator
{
    /**
     * @return array{before: int|float, after: int|float, delta: float, change_percent: float}|null
     */
    public function calculate(int|float|null $before, int|float|null $after): ?array
    {
        if (null === $before || null === $after) {
            return null;
        }

        $percent = match (true) {
            $before > 0 => round((($after - $before) / $before) * 100, 1),
            $after > 0 => 100.0,
            default => 0.0,
        };

        return [
            'before' => $before,
            'after' => $after,
            'delta' => round($after - $before, 2),
            'change_percent' => $percent,
        ];
    }
}
