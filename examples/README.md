# Laravel Package Examples

Reference snippets for integrating ml-idea agents in Laravel apps.

| Path | Description |
|---|---|
| [`custom_tool.example.php`](custom_tool.example.php) | Minimal custom tool template |
| [`register_tools.example.php`](register_tools.example.php) | Config + ServiceProvider registration |
| [`ai-admin/`](ai-admin/) | **AI admin demo** — tools, HITL, controller, routes, config snippet |
| [`agent_runs_migration.example.php`](agent_runs_migration.example.php) | DB audit log table for `MLIDEA_LOGGING_DRIVER=database` |
| [`AgentRun.model.example.php`](AgentRun.model.example.php) | Optional Eloquent model to query `agent_runs` |
| [`LogAgentRunListener.example.php`](LogAgentRunListener.example.php) | Event listeners for `AgentRunCompleted` |
| [`AdminAgentStreamController.example.php`](AdminAgentStreamController.example.php) | SSE streaming chat endpoint |

Standalone PHP demos (same domain, no Laravel): [`../../examples/ai-admin/`](../../examples/ai-admin/)
