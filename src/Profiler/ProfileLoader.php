<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Profiler;

use LukaszKrakowiak\ProfilerMcpBundle\Exception\ProfileNotFoundException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final readonly class ProfileLoader
{
    public function __construct(
        #[Autowire(service: 'profiler')]
        private Profiler $profiler,
    ) {
    }

    /**
     * @throws ProfileNotFoundException
     */
    public function load(string $token, ?string $label = null): Profile
    {
        return $this->profiler->loadProfile($token)
            ?? throw ProfileNotFoundException::forToken($token, $label);
    }
}
