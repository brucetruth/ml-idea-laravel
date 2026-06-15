<?php

declare(strict_types=1);

namespace App\Listeners;

use ML\IDEA\Laravel\Events\AgentRunCompleted;
use ML\IDEA\Laravel\Events\AgentAwaitingApproval;

/**
 * Example listeners — register in EventServiceProvider.
 *
 * Copy to app/Listeners/ and wire:
 *
 *   AgentRunCompleted::class => [LogAgentRun::class],
 *   AgentAwaitingApproval::class => [NotifyAdminForApproval::class],
 */
final class LogAgentRun
{
    public function handle(AgentRunCompleted $event): void
    {
        logger()->info('Agent run completed', [
            'session_id' => $event->sessionId,
            'stop_reason' => $event->result['stop_reason'] ?? null,
            'tool_calls' => count($event->result['tool_calls'] ?? []),
            'answer' => mb_substr((string) ($event->result['answer'] ?? ''), 0, 200),
        ]);
    }
}

final class NotifyAdminForApproval
{
    public function handle(AgentAwaitingApproval $event): void
    {
        // Slack, email, admin inbox notification
        logger()->warning('Agent awaiting approval', [
            'session_id' => $event->sessionId,
            'summary' => $event->context->summary,
            'recommended_action' => $event->context->recommendedAction,
        ]);
    }
}
