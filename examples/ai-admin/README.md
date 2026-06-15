# Laravel AI Admin Example

Copy this folder into a Laravel app (or copy individual files) to run an **AI admin assistant** with custom tools (read, write, HITL).

Standalone PHP demos (no Laravel) live in [`examples/ai-admin/`](../../../examples/ai-admin/) at the repo root.

## What's in this folder

| Path | Copy to Laravel app |
|---|---|
| `Support/AdminStore.php` | `app/AiAdmin/Support/AdminStore.php` |
| `Tools/*.php` | `app/AiAdmin/Tools/*.php` |
| `AiAdminAgentToolsProvider.example.php` | `app/Providers/AiAdminAgentToolsProvider.php` |
| `AdminAgentController.example.php` | `app/Http/Controllers/AdminAgentController.php` |
| `routes.example.php` | merge into `routes/api.php` |
| `config.mlidea.snippet.php` | merge into `config/mlidea.php` |
| `config.mlidea.autonomous.snippet.php` | autonomous tool allow-list (no HITL jobs) |
| `ProcessAutonomousSupportTicketJob.example.php` | `app/Jobs/ProcessAutonomousSupportTicketJob.php` |

## 1. Install

```bash
composer require brucetruth/ml-idea-laravel
php artisan vendor:publish --tag=mlidea-config
```

## 2. Copy files

```bash
# From your Laravel project root, after cloning ml-idea:
cp -R vendor/brucetruth/ml-idea-laravel/examples/ai-admin/Support app/AiAdmin/
cp -R vendor/brucetruth/ml-idea-laravel/examples/ai-admin/Tools app/AiAdmin/
```

Or copy from this monorepo path: `packages/laravel/examples/ai-admin/`.

Register the provider in `bootstrap/providers.php` (Laravel 11+) or `config/app.php`:

```php
App\Providers\AiAdminAgentToolsProvider::class,
```

## 3. Configure

See `config.mlidea.snippet.php`. Key settings:

```env
MLIDEA_MODEL_DRIVER=openai
OPENAI_API_KEY=sk-...
MLIDEA_AGENT_NAME=AiAdmin
MLIDEA_PAUSE_FOR_APPROVAL=true
MLIDEA_STORE_DRIVER=redis
```

## 4. Register tools

**Option A — Service provider** (see `AiAdminAgentToolsProvider.example.php`):

```php
MlIdeaAgent::registerToolClass(ListUsersTool::class);
```

**Option B — config** (`config/mlidea.php`):

```php
'tools' => [
    \App\AiAdmin\Tools\ListUsersTool::class,
    // ...
],
```

Laravel resolves each class from the container, so tools can inject `AdminStore`, Eloquent repositories, etc.

## 5. HTTP API — how admins talk to the agent

Admins send **messages**, not tool names. The model reads tool schemas and picks steps.

Full guide: [`examples/ai-admin/INTERACTION.md`](../../../examples/ai-admin/INTERACTION.md)

Wire `AdminAgentController.example.php` and `routes.example.php`. Protect with `auth` + admin middleware.

**Turn 1 — describe the situation:**

```http
POST /api/admin/ai/chat
Content-Type: application/json

{
  "session_id": "admin-ada-2024-06-13",
  "message": "Customer Bob (user #2) says order #101 was a duplicate charge. Check his profile and orders and summarize."
}
```

**Turn 2 — same session, follow-up action:**

```http
POST /api/admin/ai/chat

{
  "session_id": "admin-ada-2024-06-13",
  "message": "Refund order #101. Reason: duplicate charge, ticket #8842."
}
```

**If high-risk tool needs approval:**

```http
POST /api/admin/ai/approve

{
  "session_id": "admin-ada-2024-06-13",
  "state": { ... from chat response ... },
  "approval_token": "...",
  "approved": true
}
```

Standalone multi-turn demo (no Laravel): `php examples/ai-admin/run_admin_user_story.php`

**Customer submits refund → agent triage:**

```bash
php examples/ai-admin/run_refund_request_workflow.php
php examples/ai-admin/run_refund_request_workflow.php deny-demo
```

Laravel: `RefundRequestController.example.php`, `ProcessRefundRequestJob.example.php`, `AdminRefundApprovalController.example.php`

When the agent pauses on `refund_order`, use **`AgentApprovalContext`** — stores a human-readable summary + investigation for the admin UI, and wraps `resumeWithApproval()` so controllers stay simple. See `refund_approval_routes.example.php` and `refund_requests_migration.example.php`.

**Autonomous support triage (no HITL):**

```bash
php examples/ai-admin/run_autonomous_admin.php
```

Laravel: `ProcessAutonomousSupportTicketJob.example.php`, `config.mlidea.autonomous.snippet.php`

## 6. Tools

| Tool | Risk | Action |
|---|---|---|
| `list_users` | low | Browse users |
| `get_user` | low | Inspect one user |
| `list_orders` | low | Browse orders |
| `add_user_note` | low | Write internal note (auto) |
| `tag_order` | low | Tag order internally (auto) |
| `update_user_role` | medium | Change role (auto) |
| `update_support_ticket_status` | medium | Update ticket (auto) |
| `ban_user` | **high** | Ban account (HITL) |
| `refund_order` | **high** | Refund order (HITL) |

Replace `AdminStore` with your repositories when moving beyond the demo.

## Standalone demo (no Laravel)

```bash
php examples/ai-admin/run_admin_agent.php "List all users"
```

## Eval in CI

```bash
php artisan mlidea:agent-eval tests/fixtures/agent_eval_heuristic.json
```
