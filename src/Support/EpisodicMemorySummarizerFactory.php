<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use InvalidArgumentException;
use ML\IDEA\RAG\Agents\LlmEpisodicMemorySummarizer;
use ML\IDEA\RAG\Agents\TruncatingEpisodicMemorySummarizer;
use ML\IDEA\RAG\Contracts\EpisodicMemorySummarizerInterface;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\LLM\LlmClientFactory;

final class EpisodicMemorySummarizerFactory
{
    /** @param array<string, mixed> $config */
    public static function make(array $config): EpisodicMemorySummarizerInterface
    {
        $driver = (string) ($config['summarizer'] ?? 'truncating');

        return match ($driver) {
            'truncating' => new TruncatingEpisodicMemorySummarizer(),
            'llm' => new LlmEpisodicMemorySummarizer(self::llmClient($config)),
            default => throw new InvalidArgumentException(sprintf('Unsupported ml-idea memory summarizer: %s', $driver)),
        };
    }

    /** @param array<string, mixed> $config */
    private static function llmClient(array $config): LlmClientInterface
    {
        $llmConfig = is_array($config['llm'] ?? null) ? $config['llm'] : [];

        return LlmClientFactory::fromEnv(isset($llmConfig['provider']) ? (string) $llmConfig['provider'] : null);
    }
}
