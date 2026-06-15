<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use InvalidArgumentException;
use ML\IDEA\RAG\Agents\FileToolIdempotencyStore;
use ML\IDEA\RAG\Agents\InMemoryToolIdempotencyStore;
use ML\IDEA\RAG\Contracts\ToolIdempotencyStoreInterface;

final class ToolIdempotencyStoreFactory
{
    /** @param array<string, mixed> $config */
    public static function make(array $config, ?string $defaultPath = null): ?ToolIdempotencyStoreInterface
    {
        $driver = (string) ($config['driver'] ?? 'none');

        return match ($driver) {
            'none', 'noop' => null,
            'memory' => new InMemoryToolIdempotencyStore(),
            'file' => new FileToolIdempotencyStore(self::resolvePath($config, $defaultPath)),
            default => throw new InvalidArgumentException(sprintf('Unsupported ml-idea idempotency driver: %s', $driver)),
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

        return sys_get_temp_dir() . '/mlidea-idempotency';
    }
}
