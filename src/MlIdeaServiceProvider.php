<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel;

use Illuminate\Support\ServiceProvider;
use ML\IDEA\Laravel\Console\AgentEvalCommand;

final class MlIdeaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mlidea.php', 'mlidea');

        $this->app->singleton(ToolRoutingAgentManager::class, function ($app): ToolRoutingAgentManager {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('mlidea', []);

            if (!isset($config['store']['path']) || $config['store']['path'] === null || $config['store']['path'] === '') {
                $config['store']['path'] = $app->storagePath('app/mlidea/agent-sessions');
            }

            if (!isset($config['logging']['path']) || $config['logging']['path'] === null || $config['logging']['path'] === '') {
                $config['logging']['path'] = $app->storagePath('logs/mlidea-agent-runs.jsonl');
            }

            if (!isset($config['idempotency']['path']) || $config['idempotency']['path'] === null || $config['idempotency']['path'] === '') {
                $config['idempotency']['path'] = $app->storagePath('app/mlidea/idempotency');
            }

            if (!isset($config['memory']['path']) || $config['memory']['path'] === null || $config['memory']['path'] === '') {
                $config['memory']['path'] = $app->storagePath('app/mlidea/agent-memory');
            }

            $config['logging_path'] = $config['logging']['path'];
            $config['idempotency_path'] = $config['idempotency']['path'];
            $config['memory_path'] = $config['memory']['path'];

            return new ToolRoutingAgentManager(
                static fn (string $class): object => $app->make($class),
                $config,
                $app->bound('db') ? $app['db'] : null,
                $app->bound('events') ? $app['events'] : null,
                $app->bound('log') ? $app['log'] : null,
            );
        });

        $this->app->alias(ToolRoutingAgentManager::class, 'mlidea.agent');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mlidea.php' => $this->app->configPath('mlidea.php'),
            ], 'mlidea-config');

            $this->commands([
                AgentEvalCommand::class,
            ]);
        }
    }
}
