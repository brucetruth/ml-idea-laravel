<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use ML\IDEA\Laravel\Facades\MlIdeaAgent;

/**
 * Autonomous AI admin — no human approval step.
 *
 * Agent calls low/medium-risk tools that write to your DB (notes, tags, ticket status).
 * High-risk tools (refund_order, ban_user) should NOT be registered for this job.
 *
 * Copy to app/Jobs/ProcessAutonomousSupportTicketJob.php
 *
 * Dispatch after customer opens a ticket:
 *   ProcessAutonomousSupportTicketJob::dispatch($ticket->id);
 */
final class ProcessAutonomousSupportTicketJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public readonly int $supportTicketId)
    {
    }

    public function handle(): void
    {
        $ticket = SupportTicket::query()->findOrFail($this->supportTicketId);
        $ticket->update(['status' => 'agent_review']);

        $prompt = implode("\n", [
            sprintf('AUTONOMOUS SUPPORT TICKET #%d', $ticket->id),
            sprintf('- user_id=%d', $ticket->user_id),
            sprintf('- order_id=%s', $ticket->order_id ?? 'unknown'),
            sprintf('- subject: %s', $ticket->subject),
            sprintf('- body: %s', $ticket->body),
            '',
            'Triage without human approval:',
            '1) Verify customer and related orders (read-only tools).',
            '2) add_user_note with what you found.',
            '3) tag_order if billing/fraud/vip applies.',
            '4) update_support_ticket_status to pending or resolved.',
            '5) Final summary for the support inbox.',
        ]);

        $sessionId = 'support-ticket-' . $ticket->id;
        $result = MlIdeaAgent::chat($prompt, $sessionId);

        // No AgentApprovalContext — low/medium tools already persisted via tool invoke()
        $ticket->update([
            'status' => str_contains(strtolower((string) $result['answer']), 'resolved') ? 'resolved' : 'pending',
            'agent_summary' => $result['answer'],
        ]);

        // Optional: SupportTicketTriaged::dispatch($ticket);
    }
}
