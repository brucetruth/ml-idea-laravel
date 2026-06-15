<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use ML\IDEA\Laravel\ToolRoutingAgentManager;
use ML\IDEA\RAG\Agents\AgentStreamEvent;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;

/**
 * @method static ToolRoutingAgent make(array $overrides = [])
 * @method static array<string, mixed> chat(string $message, ?string $sessionId = null)
 * @method static \Generator<int, AgentStreamEvent, mixed, array<string, mixed>> chatStream(string $message, ?string $sessionId = null)
 * @method static ToolRoutingAgentManager registerHandoff(string $name, ToolRoutingAgent $agent, string $description = '')
 * @method static ToolRoutingAgentManager registerTool(\ML\IDEA\RAG\Contracts\ToolInterface $tool)
 * @method static ToolRoutingAgentManager registerToolClass(class-string $toolClass)
 * @method static array<int, string> toolNames()
 * @method static \ML\IDEA\Laravel\Support\AgentApprovalContext|null approvalContextFromResult(array $result)
 * @method static array<string, mixed> resumeWithApproval(\ML\IDEA\RAG\Agents\AgentState $state, bool $approved, string $approvalToken, ?string $sessionId = null)
 *
 * @see ToolRoutingAgentManager
 */
final class MlIdeaAgent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mlidea.agent';
    }
}
