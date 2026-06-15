<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use ML\IDEA\Laravel\Facades\MlIdeaAgent;
use ML\IDEA\Laravel\Support\AgentApprovalContext;

/**
 * Dispatched after customer submits refund — agent investigates and decides.
 * Copy to app/Jobs/ProcessRefundRequestJob.php
 */
final class ProcessRefundRequestJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public readonly int $refundRequestId)
    {
    }

    public function handle(): void
    {
        $request = RefundRequest::query()->findOrFail($this->refundRequestId);
        $request->update(['status' => 'agent_review']);

        // Structured prompt — customer story + instructions (not free-form admin chat)
        $prompt = implode("\n", [
            sprintf('NEW REFUND REQUEST #%d (status: pending)', $request->id),
            sprintf('- customer user_id=%d', $request->user_id),
            sprintf('- order_id=%d', $request->order_id),
            sprintf('- customer reason: %s', $request->reason),
            '',
            'Review this request:',
            '1) Verify the customer and order with read-only tools.',
            '2) If eligible, call refund_order (will pause for admin approval).',
            '3) If not eligible, deny with a final answer (do not call refund_order).',
        ]);

        $sessionId = 'refund-request-' . $request->id;
        $result = MlIdeaAgent::chat($prompt, $sessionId);

        $approvalContext = MlIdeaAgent::approvalContextFromResult($result);

        if ($approvalContext !== null) {
            // Agent recommends proceed — save human-readable review + resume handles for admin UI
            $request->update([
                'status' => 'awaiting_admin',
                'agent_decision' => $approvalContext->summary,
                'agent_review_context' => $approvalContext->toStorage(),
            ]);

            return;
        }

        $status = str_contains(strtolower((string) $result['answer']), 'deny') ? 'denied' : 'escalated';
        if (str_contains(strtolower((string) $result['answer']), 'approved')) {
            $status = 'approved';
        }

        $request->update([
            'status' => $status,
            'agent_decision' => $result['answer'],
        ]);

        // Notify customer: RefundRequestResolved::dispatch($request);
    }
}
