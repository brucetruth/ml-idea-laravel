<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Routing model
    |--------------------------------------------------------------------------
    |
    | Supported drivers: heuristic, openai, anthropic, ollama, azure_openai
    |
    */
    'model' => [
        'driver' => env('MLIDEA_MODEL_DRIVER', 'heuristic'),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY', ''),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20240620'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        ],
        'ollama' => [
            'model' => env('OLLAMA_MODEL', 'llama3.1'),
            'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
            'native_tools' => env('OLLAMA_NATIVE_TOOLS', true),
            'api_key' => env('OLLAMA_API_KEY', ''),
            'http_timeout' => (int) env('MLIDEA_HTTP_TIMEOUT', 120),
        ],
        'azure_openai' => [
            'api_key' => env('AZURE_OPENAI_API_KEY', ''),
            'endpoint' => env('AZURE_OPENAI_ENDPOINT', ''),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', ''),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent defaults
    |--------------------------------------------------------------------------
    */
    'agent' => [
        'name' => env('MLIDEA_AGENT_NAME', 'LaravelAgent'),
        'max_iterations' => (int) env('MLIDEA_MAX_ITERATIONS', 8),
        'system_prompt' => env('MLIDEA_SYSTEM_PROMPT'),
        'features' => [],
        'include_planning_prompt' => (bool) env('MLIDEA_INCLUDE_PLANNING_PROMPT', true),
        'pause_for_approval' => (bool) env('MLIDEA_PAUSE_FOR_APPROVAL', false),
        'max_tool_calls' => (int) env('MLIDEA_MAX_TOOL_CALLS', 16),
        'order_tool_calls_by_risk' => (bool) env('MLIDEA_ORDER_TOOL_CALLS_BY_RISK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool idempotency (prevent double-refund on retry/HITL resume)
    |--------------------------------------------------------------------------
    |
    | driver: none | memory | file
    | Tools implementing IdempotentToolInterface are deduplicated by idempotencyKey().
    |
    */
    'idempotency' => [
        'driver' => env('MLIDEA_IDEMPOTENCY_DRIVER', 'file'),
        'path' => env('MLIDEA_IDEMPOTENCY_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Episodic session memory
    |--------------------------------------------------------------------------
    |
    | driver: none | memory | file
    | Recalls prior tool outcomes when routing context is windowed (long sessions).
    |
    */
    'memory' => [
        'driver' => env('MLIDEA_MEMORY_DRIVER', 'file'),
        'path' => env('MLIDEA_MEMORY_PATH'),
        'summarizer' => env('MLIDEA_MEMORY_SUMMARIZER', 'truncating'),
        'llm' => [
            'provider' => env('MLIDEA_MEMORY_LLM_PROVIDER', 'echo'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool circuit breaker
    |--------------------------------------------------------------------------
    |
    | Opens after repeated tool_exception/timeout failures; blocks until cooldown.
    |
    */
    'circuit_breaker' => [
        'enabled' => (bool) env('MLIDEA_CIRCUIT_BREAKER', false),
        'failure_threshold' => (int) env('MLIDEA_CIRCUIT_FAILURE_THRESHOLD', 3),
        'cooldown_seconds' => (int) env('MLIDEA_CIRCUIT_COOLDOWN_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallel tool execution (ext-parallel)
    |--------------------------------------------------------------------------
    |
    | When enabled, batches of ParallelInvokableToolInterface tools (non-high-risk)
    | may run via ext-parallel workers. Falls back to sequential when unavailable.
    | autoload: path to vendor/autoload.php for worker runtimes
    |
    */
    'parallel_tools' => [
        'enabled' => (bool) env('MLIDEA_PARALLEL_TOOLS', false),
        'autoload' => env('MLIDEA_PARALLEL_AUTOLOAD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime budgets (token/cost caps)
    |--------------------------------------------------------------------------
    */
    'budget' => [
        'max_runtime_ms' => (int) env('MLIDEA_MAX_RUNTIME_MS', 30000),
        'max_tokens' => (int) env('MLIDEA_MAX_TOKENS', 0),
        'max_estimated_cost' => (float) env('MLIDEA_MAX_ESTIMATED_COST', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel events
    |--------------------------------------------------------------------------
    |
    | Dispatches AgentRunCompleted and AgentAwaitingApproval after each run.
    |
    */
    'events' => [
        'enabled' => (bool) env('MLIDEA_EVENTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered tools (container-resolvable class names)
    |--------------------------------------------------------------------------
    |
    | List ToolInterface classes here. Laravel resolves each from the container,
    | so tools can type-hint services (repositories, HTTP clients, etc.).
    |
    | You can also register tools at runtime:
    |   MlIdeaAgent::registerTool($app->make(ListUsersTool::class));
    |
    | See packages/laravel/README.md and packages/laravel/examples/ai-admin/
    |
    */
    'tools' => [
        \ML\IDEA\RAG\Tools\MathTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Session persistence
    |--------------------------------------------------------------------------
    |
    | driver: file | redis | auto
    | path: defaults to storage_path('app/mlidea/agent-sessions') when null
    |
    */
    'store' => [
        'driver' => env('MLIDEA_STORE_DRIVER', 'auto'),
        'path' => env('MLIDEA_STORE_PATH'),
        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_DB', 0),
            'prefix' => env('MLIDEA_REDIS_PREFIX', 'mlidea:agent:'),
            'ttl' => (int) env('MLIDEA_REDIS_TTL', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing
    |--------------------------------------------------------------------------
    |
    | Supported drivers: noop, recording
    | Use OpenTelemetryAgentTracer manually when open-telemetry/sdk is installed.
    |
    */
    'tracing' => [
        'driver' => env('MLIDEA_TRACING_DRIVER', 'noop'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent run audit logging
    |--------------------------------------------------------------------------
    |
    | Supported drivers: noop, jsonl, database, psr3, multi
    | multi: set drivers => ['jsonl', 'database'] to log to both
    | Each completed chat()/resume logs tool_calls, decisions, usage, stop_reason
    | (secrets redacted).
    |
    | jsonl default path: storage/logs/mlidea-agent-runs.jsonl
    | database: run examples/agent_runs_migration.example.php first
    |
    */
    'logging' => [
        'driver' => env('MLIDEA_LOGGING_DRIVER', 'noop'),
        'path' => env('MLIDEA_LOGGING_PATH'),
        'connection' => env('MLIDEA_LOGGING_CONNECTION'),
        'table' => env('MLIDEA_LOGGING_TABLE', 'agent_runs'),
        'drivers' => ['jsonl', 'database'],
        'level' => env('MLIDEA_LOGGING_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context windowing
    |--------------------------------------------------------------------------
    */
    'context' => [
        'enabled' => (bool) env('MLIDEA_CONTEXT_ENABLED', true),
        'max_messages' => (int) env('MLIDEA_CONTEXT_MAX_MESSAGES', 24),
        'max_tool_output_chars' => (int) env('MLIDEA_CONTEXT_MAX_TOOL_OUTPUT_CHARS', 4000),
    ],
];
