# ml-idea Laravel Bridge

Laravel integration for [ml-idea](https://github.com/brucetruth/ml-idea) `ToolRoutingAgent`: config-driven models, session stores, tracing, and **custom tools** via the container.

## Install

```bash
composer require brucetruth/ml-idea-laravel
php artisan vendor:publish --tag=mlidea-config
```

## Quick start

```php
use ML\IDEA\Laravel\Facades\MlIdeaAgent;

$result = MlIdeaAgent::chat('calculate sqrt(144)+10', sessionId: 'admin-1');

echo $result['answer'];
```

Configure via `.env`:

```env
MLIDEA_MODEL_DRIVER=heuristic   # openai | anthropic | ollama | azure_openai
MLIDEA_STORE_DRIVER=auto
MLIDEA_TRACING_DRIVER=noop      # recording for debug
MLIDEA_LOGGING_DRIVER=noop      # jsonl | database
MLIDEA_LOGGING_PATH=            # jsonl: default storage/logs/mlidea-agent-runs.jsonl
MLIDEA_LOGGING_CONNECTION=      # database: optional connection name
MLIDEA_LOGGING_TABLE=agent_runs  # database table name
MLIDEA_PAUSE_FOR_APPROVAL=false
```

Session files default to `storage/app/mlidea/agent-sessions`.

## Custom tools

Custom tools are **first-class**. Each tool is a class implementing `ML\IDEA\RAG\Contracts\ToolInterface`. For production agents, also implement `ToolSchemaInterface` (JSON Schema, examples, risk level).

### 1. Create a tool

```php
namespace App\AiAdmin\Tools;

use App\Repositories\UserRepository;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class ListUsersTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly UserRepository $users) {}

    public function name(): string { return 'list_users'; }

    public function description(): string
    {
        return 'List users with optional role filter.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'role' => ['type' => 'string'],
            ],
        ];
    }

    public function examples(): array { return [['role' => 'admin'], []]; }

    public function riskLevel(): string { return 'low'; }

    public function invoke(array $input): string
    {
        $role = isset($input['role']) ? (string) $input['role'] : null;
        $users = $this->users->list($role);

        return json_encode(['users' => $users, 'count' => count($users)], JSON_THROW_ON_ERROR);
    }
}
```

Laravel resolves constructor dependencies automatically.

### 2. Register tools (two options)

**Option A — config** (`config/mlidea.php`):

```php
'tools' => [
    \App\AiAdmin\Tools\ListUsersTool::class,
    \App\AiAdmin\Tools\BanUserTool::class,
],
```

**Option B — runtime** (`AppServiceProvider::boot`):

```php
use ML\IDEA\Laravel\Facades\MlIdeaAgent;

MlIdeaAgent::registerToolClass(ListUsersTool::class);
// or
MlIdeaAgent::registerTool($this->app->make(ListUsersTool::class));
```

Use both if needed: config for stable defaults, runtime for conditional tools. Same tool name registered twice → runtime wins.

List registered tools:

```php
MlIdeaAgent::toolNames(); // ['list_users', 'ban_user', ...]
```

### 3. AI admin pattern

Map **one admin action → one tool** (list users, refund order, toggle feature flag, etc.). Mark destructive tools `riskLevel(): 'high'` and set:

```env
MLIDEA_PAUSE_FOR_APPROVAL=true
```

Full walkthrough with six admin tools:

- [`examples/ai-admin/`](examples/ai-admin/) — **Laravel demo** (copy `App\AiAdmin` tools, controller, routes)
- [`../../examples/ai-admin/`](../../examples/ai-admin/) — standalone PHP demos (no Laravel)

Run the standalone demo from the repo root:

```bash
php examples/ai-admin/run_admin_agent.php "List all users"
```

## Streaming

```php
foreach (MlIdeaAgent::chatStream($message, $sessionId) as $event) {
    // SSE: echo "data: " . json_encode($event) . "\n\n";
}
```

## Human-in-the-loop

High-risk tools pause with `stop_reason: awaiting_approval`. Persist `state` + `approval_token`, then:

```php
$agent = MlIdeaAgent::make();
$result = $agent->resumeWithApproval(
    AgentState::fromArray($savedState),
    approved: true,
    approvalToken: $token,
);
```

See `examples/ai-admin/run_admin_agent_hitl.php`.

## Specialist handoffs

```php
MlIdeaAgent::registerHandoff('billing', $billingAgent, 'Handles refunds and invoices');
```

## Artisan eval

```bash
php artisan mlidea:agent-eval tests/fixtures/agent_eval_heuristic.json --min-pass-rate=1.0
```

## Package examples

See [`examples/`](examples/) in this package for copy-paste snippets.

## API reference

| Class / Facade | Purpose |
|---|---|
| `MlIdeaAgent` | Facade for `ToolRoutingAgentManager` |
| `ToolRoutingAgentManager::make()` | Build configured agent |
| `::registerTool()` / `::registerToolClass()` | Add custom tools |
| `::registerHandoff()` | Multi-agent delegation |
| `::chat()` / `::chatStream()` | Run agent |
| `MLIDEA_LOGGING_DRIVER=jsonl` | Append audit JSONL per run |
| `MLIDEA_LOGGING_DRIVER=database` | Insert row into `agent_runs` table |

## Agent run audit log (database)

1. Copy migration from `examples/agent_runs_migration.example.php` and run `php artisan migrate`
2. Optional: copy `examples/AgentRun.model.example.php` to `app/Models/AgentRun.php`
3. Configure:

```env
MLIDEA_LOGGING_DRIVER=database
MLIDEA_LOGGING_TABLE=agent_runs
```

Every `MlIdeaAgent::chat()` / `resumeWithApproval()` inserts one row with `tool_calls`, `decisions`, `usage`, `stop_reason` (redacted).

Query recent runs:

```php
AgentRun::query()
    ->where('session_id', 'support-ticket-501')
    ->latest('logged_at')
    ->get();
```

## Events (v1.8)

`AgentRunCompleted` and `AgentAwaitingApproval` dispatch after each run (`MLIDEA_EVENTS_ENABLED=true` by default). See `examples/LogAgentRunListener.example.php` and [`docs/AGENT_COOKBOOK.md`](../../docs/AGENT_COOKBOOK.md).

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- `brucetruth/ml-idea`

Optional: `open-telemetry/sdk`, `ext-redis`
