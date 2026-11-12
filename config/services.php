<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('LukaszKrakowiak\\ProfilerMcpBundle\\', '../src/')
        ->exclude([
            '../src/ProfilerMcpBundle.php',
            '../src/Exception/',
            '../src/Metrics/CollectorResult.php',
            '../src/Metrics/ProfileMetrics.php',
        ]);
};
