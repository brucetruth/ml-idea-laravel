<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;
use ML\IDEA\RAG\LLM\AnthropicToolRoutingModel;
use ML\IDEA\RAG\LLM\AzureOpenAIToolRoutingModel;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\LLM\OllamaToolRoutingModel;
use ML\IDEA\RAG\LLM\OpenAIToolRoutingModel;

final class RoutingModelFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function make(array $config): ToolRoutingModelInterface
    {
        $driver = (string) ($config['driver'] ?? 'heuristic');

        return match ($driver) {
            'heuristic' => new HeuristicToolRoutingModel(),
            'openai' => self::openAi($config['openai'] ?? []),
            'anthropic' => self::anthropic($config['anthropic'] ?? []),
            'ollama' => self::ollama($config['ollama'] ?? []),
            'azure_openai' => self::azureOpenAi($config['azure_openai'] ?? []),
            default => throw new InvalidArgumentException(sprintf('Unsupported ml-idea model driver: %s', $driver)),
        };
    }

    /** @param array<string, mixed> $config */
    private static function openAi(array $config): OpenAIToolRoutingModel
    {
        return new OpenAIToolRoutingModel(
            apiKey: (string) ($config['api_key'] ?? ''),
            model: (string) ($config['model'] ?? 'gpt-4o-mini'),
            baseUrl: (string) ($config['base_url'] ?? 'https://api.openai.com/v1'),
        );
    }

    /** @param array<string, mixed> $config */
    private static function anthropic(array $config): AnthropicToolRoutingModel
    {
        return new AnthropicToolRoutingModel(
            apiKey: (string) ($config['api_key'] ?? ''),
            model: (string) ($config['model'] ?? 'claude-3-5-sonnet-20240620'),
            baseUrl: (string) ($config['base_url'] ?? 'https://api.anthropic.com/v1'),
        );
    }

    /** @param array<string, mixed> $config */
    private static function ollama(array $config): OllamaToolRoutingModel
    {
        $apiKey = isset($config['api_key']) ? (string) $config['api_key'] : '';
        $timeout = (int) ($config['http_timeout'] ?? 120);

        return new OllamaToolRoutingModel(
            model: (string) ($config['model'] ?? 'llama3.1'),
            baseUrl: (string) ($config['base_url'] ?? 'http://127.0.0.1:11434'),
            http: new SimpleHttpTransport($timeout > 0 ? $timeout : 120),
            useNativeTools: (bool) ($config['native_tools'] ?? true),
            apiKey: $apiKey !== '' ? $apiKey : null,
        );
    }

    /** @param array<string, mixed> $config */
    private static function azureOpenAi(array $config): AzureOpenAIToolRoutingModel
    {
        return new AzureOpenAIToolRoutingModel(
            apiKey: (string) ($config['api_key'] ?? ''),
            endpoint: (string) ($config['endpoint'] ?? ''),
            deployment: (string) ($config['deployment'] ?? ''),
            apiVersion: (string) ($config['api_version'] ?? '2024-02-15-preview'),
        );
    }
}
