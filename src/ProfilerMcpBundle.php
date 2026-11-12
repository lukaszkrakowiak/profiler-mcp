<?php

namespace LukaszKrakowiak\ProfilerMcpBundle;

use LukaszKrakowiak\ProfilerMcpBundle\Mcp\Tool\ProfilerAuditTool;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ProfilerMcpBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$container->hasExtension('mcp') || $container->getParameter('kernel.environment') !== 'dev') {
            return;
        }

        // McpBundle discovers #[McpTool] by scanning dirs relative to the project dir; add ours.
        $configurator->extension('mcp', [
            'discovery' => [
                'scan_dirs' => [
                    'src',
                    Path::makeRelative(__DIR__.'/Mcp', $container->getParameter('kernel.project_dir')),
                ],
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if ($container->getParameter('kernel.environment') !== 'dev') {
            return;
        }

        $container->register(ProfilerAuditTool::class)
            ->setArgument('$profiler', new Reference('profiler'))
            ->setAutowired(true)
            ->setAutoconfigured(true);
    }
}
