<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Profiler;

use LukaszKrakowiak\ProfilerMcpBundle\Exception\ProfileNotFoundException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Finds the newest profile that belongs to the application itself (not to the profiler/WDT or browser noise).
 */
final readonly class LatestRequestFinder
{
    private const IGNORED_URL_PATTERN = '#(/(_profiler|_wdt)|favicon\.ico)#';
    private const SEARCH_LIMIT = 20;

    public function __construct(
        #[Autowire(service: 'profiler')]
        private Profiler $profiler,
    ) {
    }

    /**
     * @throws ProfileNotFoundException
     */
    public function findToken(?string $urlContains = null): string
    {
        foreach ($this->profiler->find(null, $urlContains, self::SEARCH_LIMIT, null, null, null) as $entry) {
            if ($this->isNoise($entry['method'] ?? 'GET', $entry['url'] ?? '')) {
                continue;
            }

            return $entry['token'];
        }

        throw ProfileNotFoundException::noMatchingSession();
    }

    private function isNoise(string $method, string $url): bool
    {
        return 'OPTIONS' === $method || 1 === preg_match(self::IGNORED_URL_PATTERN, $url);
    }
}
