<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Profiler;

use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\VarDumper\Cloner\Data;

final class ControllerInfoExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(Profile $profile): array
    {
        if (!$profile->hasCollector('request')) {
            return [];
        }

        $request = $profile->getCollector('request');
        if (!$request instanceof RequestDataCollector) {
            return [];
        }

        $route = $this->unwrap($request->getRoute());
        $controller = $this->unwrap($request->getController());

        $info = ['route' => \is_string($route) && '' !== $route ? $route : null];

        if (\is_array($controller)) {
            $class = $controller['class'] ?? null;
            $method = $controller['method'] ?? null;

            $info['callable'] = ($class && $method) ? sprintf('%s::%s', $class, $method) : $class;
            $info['class'] = $class;
            $info['method'] = $method;
            $info['file'] = $controller['file'] ?? null;
            $info['line'] = $controller['line'] ?? null;
        } elseif (\is_string($controller) && 'n/a' !== $controller) {
            $info['callable'] = $controller;
        } else {
            $info['callable'] = $this->controllerFromAttributes($request);
        }

        return array_filter($info, static fn (mixed $value): bool => null !== $value);
    }

    private function controllerFromAttributes(RequestDataCollector $request): ?string
    {
        $attributes = $this->unwrap($request->getRequestAttributes());
        $controller = \is_array($attributes) ? ($attributes['_controller'] ?? null) : null;

        return match (true) {
            null === $controller => null,
            \is_string($controller) => $controller,
            default => get_debug_type($controller),
        };
    }

    private function unwrap(mixed $value): mixed
    {
        return $value instanceof Data ? $value->getValue(true) : $value;
    }
}
