<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ML\IDEA\Laravel\Support\AgentApprovalContext;
use ML\IDEA\Laravel\ToolRoutingAgentManager;

/**
 * Admin reviews agent-recommended refunds — copy to AdminRefundApprovalController.php
 *
 * Flow:
 *   1. Customer submits refund → ProcessRefundRequestJob runs agent
 *   2. Agent pauses on refund_order → job saves agent_review_context (human-readable + resume handles)
 *   3. Admin GET /refunds/{id}/review → reads summary + investigation (no agent_state in UI)
 *   4. Admin POST /refunds/{id}/decide { approved: true|false } → context.resume()
 */
final class AdminRefundApprovalController
{
    /** What the admin sees before approving — no raw agent_state exposed. */
    public function review(RefundRequest $refundRequest): JsonResponse
    {
        if ($refundRequest->status !== 'awaiting_admin') {
            return response()->json(['error' => 'Not awaiting admin review'], 422);
        }

        $context = AgentApprovalContext::fromStorage(
            is_array($refundRequest->agent_review_context) ? $refundRequest->agent_review_context : []
        );

        return response()->json([
            'refund_request' => [
                'id' => $refundRequest->id,
                'user_id' => $refundRequest->user_id,
                'order_id' => $refundRequest->order_id,
                'customer_reason' => $refundRequest->reason,
                'status' => $refundRequest->status,
            ],
            'agent_review' => $context->toReviewPayload(),
        ]);
    }

    /** Admin confirms or rejects the agent's recommended refund_order call. */
    public function decide(Request $request, RefundRequest $refundRequest): JsonResponse
    {
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($refundRequest->status !== 'awaiting_admin') {
            return response()->json(['error' => 'Not awaiting admin review'], 422);
        }

        $context = AgentApprovalContext::fromStorage(
            is_array($refundRequest->agent_review_context) ? $refundRequest->agent_review_context : []
        );

        // One line — context holds state + token + investigation; admin only sends approved + optional note
        $result = $context->resume(app(ToolRoutingAgentManager::class), (bool) $validated['approved']);

        $refundRequest->update([
            'status' => $validated['approved'] ? 'approved' : 'denied',
            'agent_decision' => $result['answer'],
            'admin_note' => $validated['admin_note'] ?? null,
        ]);

        return response()->json([
            'refund_request' => $refundRequest->fresh(),
            'agent_outcome' => [
                'answer' => $result['answer'],
                'stop_reason' => $result['stop_reason'],
            ],
        ]);
    }
}
