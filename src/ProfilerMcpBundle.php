<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ProfilerMcpBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('profiler_base_url')
                    ->info('Base URL used to build links to the web profiler.')
                    ->defaultValue('http://localhost:8000')
                ->end()
                ->arrayNode('thresholds')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('response_time_ms')->min(0)->defaultValue(300)->end()
                        ->integerNode('memory_mb')->min(0)->defaultValue(64)->end()
                        ->integerNode('twig_ms')->min(0)->defaultValue(100)->end()
                        ->integerNode('n_plus_one_repeats')
                            ->info('Same SQL executed at least this many times is reported as a possible N+1.')
                            ->min(2)->defaultValue(3)
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$this->isEnabled($container) || !$container->hasExtension('mcp')) {
            return;
        }

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
        if (!$this->isEnabled($container)) {
            return;
        }

        $configurator->parameters()
            ->set('profiler_mcp.profiler_base_url', $config['profiler_base_url']);

        foreach ($config['thresholds'] as $name => $value) {
            $configurator->parameters()->set('profiler_mcp.thresholds.'.$name, $value);
        }

        $configurator->import('../config/services.php');
    }

    private function isEnabled(ContainerBuilder $container): bool
    {
        return 'dev' === $container->getParameter('kernel.environment');
    }
}
