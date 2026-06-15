<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use ML\IDEA\RAG\Agents\ToolCircuitBreaker;

final class ToolCircuitBreakerFactory
{
    /** @param array<string, mixed> $config */
    public static function make(array $config): ?ToolCircuitBreaker
    {
        if (($config['enabled'] ?? false) !== true) {
            return null;
        }

        return new ToolCircuitBreaker(
            failureThreshold: (int) ($config['failure_threshold'] ?? 3),
            cooldownSeconds: (int) ($config['cooldown_seconds'] ?? 60),
        );
    }
}
