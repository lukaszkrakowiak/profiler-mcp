<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Metrics\Collector;

use LukaszKrakowiak\ProfilerMcpBundle\Metrics\CollectorResult;
use LukaszKrakowiak\ProfilerMcpBundle\Metrics\MetricCollectorInterface;
use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Profiler\Profile;

final readonly class TwigMetricCollector implements MetricCollectorInterface
{
    public function __construct(
        #[Autowire(param: 'profiler_mcp.thresholds.twig_ms')]
        private int $thresholdMs,
    ) {
    }

    public function collect(Profile $profile): ?CollectorResult
    {
        if (!$profile->hasCollector('twig')) {
            return null;
        }

        $twig = $profile->getCollector('twig');
        if (!$twig instanceof TwigDataCollector) {
            return null;
        }

        $twigMs = round($twig->getTime() * 1000, 2);
        $templateCount = $twig->getTemplateCount();

        return new CollectorResult(
            ['twig' => [
                'render_time_ms' => $twigMs,
                'template_count' => $templateCount,
                'templates' => $twig->getTemplates(),
            ]],
            $twigMs > $this->thresholdMs ? [sprintf('Slow Twig rendering: %.2f ms across %d templates', $twigMs, $templateCount)] : [],
        );
    }
}
