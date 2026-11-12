<?php

declare(strict_types=1);

namespace LukaszKrakowiak\ProfilerMcpBundle\Exception;

final class ProfileNotFoundException extends \RuntimeException
{
    public static function forToken(string $token, ?string $label = null): self
    {
        return new self(sprintf('Could not load %sprofile for token "%s".', $label ? sprintf('"%s" ', $label) : '', $token));
    }

    public static function noMatchingSession(): self
    {
        return new self('No matching profiler session found. Make a request to the app first.');
    }
}
