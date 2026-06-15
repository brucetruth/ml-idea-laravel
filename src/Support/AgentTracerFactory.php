<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Agents\NoOpAgentTracer;
use ML\IDEA\RAG\Agents\RecordingAgentTracer;
use ML\IDEA\RAG\Contracts\AgentTracerInterface;

final class AgentTracerFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function make(array $config): AgentTracerInterface
    {
        $driver = (string) ($config['driver'] ?? 'noop');

        return match ($driver) {
            'noop' => new NoOpAgentTracer(),
            'recording' => new RecordingAgentTracer(),
            default => throw new InvalidArgumentException(sprintf('Unsupported ml-idea tracing driver: %s', $driver)),
        };
    }
}
