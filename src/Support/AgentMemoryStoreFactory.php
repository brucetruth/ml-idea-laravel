<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use InvalidArgumentException;
use ML\IDEA\RAG\Agents\FileAgentMemoryStore;
use ML\IDEA\RAG\Agents\InMemoryAgentMemoryStore;
use ML\IDEA\RAG\Contracts\AgentMemoryStoreInterface;
use ML\IDEA\RAG\Agents\TruncatingEpisodicMemorySummarizer;
use ML\IDEA\RAG\Contracts\EpisodicMemorySummarizerInterface;

final class AgentMemoryStoreFactory
{
    /** @param array<string, mixed> $config */
    public static function make(
        array $config,
        ?string $defaultPath = null,
        ?EpisodicMemorySummarizerInterface $summarizer = null,
    ): ?AgentMemoryStoreInterface {
        $driver = (string) ($config['driver'] ?? 'none');
        $summarizer ??= new TruncatingEpisodicMemorySummarizer();

        return match ($driver) {
            'none', 'noop' => null,
            'memory' => new InMemoryAgentMemoryStore($summarizer),
            'file' => new FileAgentMemoryStore(self::resolvePath($config, $defaultPath), $summarizer),
            default => throw new InvalidArgumentException(sprintf('Unsupported ml-idea memory driver: %s', $driver)),
        };
    }

    /** @param array<string, mixed> $config */
    private static function resolvePath(array $config, ?string $defaultPath): string
    {
        $path = $config['path'] ?? null;
        if (is_string($path) && $path !== '') {
            return $path;
        }

        if ($defaultPath !== null && $defaultPath !== '') {
            return $defaultPath;
        }

        return sys_get_temp_dir() . '/mlidea-agent-memory';
    }
}
