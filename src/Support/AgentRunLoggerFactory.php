<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use InvalidArgumentException;
use ML\IDEA\RAG\Agents\JsonlAgentRunLogger;
use ML\IDEA\RAG\Agents\MultiAgentRunLogger;
use ML\IDEA\RAG\Agents\NoOpAgentRunLogger;
use ML\IDEA\RAG\Agents\Psr3AgentRunLogger;
use ML\IDEA\RAG\Contracts\AgentRunLoggerInterface;
use Psr\Log\LoggerInterface;

final class AgentRunLoggerFactory
{
    /**
     * @param array<string, mixed> $config
     * @param mixed $database Illuminate\Database\DatabaseManager|Connection|null
     * @param mixed $logger PSR-3 LoggerInterface|null
     */
    public static function make(
        array $config,
        ?string $defaultPath = null,
        mixed $database = null,
        mixed $logger = null,
    ): AgentRunLoggerInterface {
        $driver = (string) ($config['driver'] ?? 'noop');

        return match ($driver) {
            'noop' => new NoOpAgentRunLogger(),
            'jsonl' => new JsonlAgentRunLogger(self::resolvePath($config, $defaultPath)),
            'database' => new DatabaseAgentRunLogger(
                DatabaseAgentRunLogger::connectionFrom(
                    self::requireDatabase($database),
                    isset($config['connection']) ? (string) $config['connection'] : null,
                ),
                (string) ($config['table'] ?? 'agent_runs'),
            ),
            'psr3' => new Psr3AgentRunLogger(
                self::requireLogger($logger),
                (string) ($config['message'] ?? 'agent.run.completed'),
                (string) ($config['level'] ?? 'info'),
            ),
            'multi' => self::makeMulti($config, $defaultPath, $database, $logger),
            default => throw new InvalidArgumentException(sprintf('Unsupported ml-idea logging driver: %s', $driver)),
        };
    }

    /**
     * @param array<string, mixed> $config
     * @param mixed $database
     * @param mixed $logger
     */
    private static function makeMulti(
        array $config,
        ?string $defaultPath,
        mixed $database,
        mixed $logger,
    ): MultiAgentRunLogger {
        $drivers = is_array($config['drivers'] ?? null) ? $config['drivers'] : ['jsonl'];
        $loggers = [];

        foreach ($drivers as $driver) {
            if (!is_string($driver) || $driver === '') {
                continue;
            }
            $loggers[] = self::make(
                array_merge($config, ['driver' => $driver]),
                $defaultPath,
                $database,
                $logger,
            );
        }

        if ($loggers === []) {
            return new MultiAgentRunLogger([new NoOpAgentRunLogger()]);
        }

        return new MultiAgentRunLogger($loggers);
    }

    private static function requireDatabase(mixed $database): mixed
    {
        if ($database === null) {
            throw new InvalidArgumentException(
                'MLIDEA_LOGGING_DRIVER=database requires Laravel db service (run migrations for agent_runs).',
            );
        }

        return $database;
    }

    private static function requireLogger(mixed $logger): LoggerInterface
    {
        if ($logger instanceof LoggerInterface) {
            return $logger;
        }

        throw new InvalidArgumentException(
            'MLIDEA_LOGGING_DRIVER=psr3 requires a PSR-3 logger (Laravel Log facade).',
        );
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

        return sys_get_temp_dir() . '/mlidea-agent-runs.jsonl';
    }
}
