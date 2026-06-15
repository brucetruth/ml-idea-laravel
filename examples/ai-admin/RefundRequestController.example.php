<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AiAdmin\Support\AdminStore;
use App\Jobs\ProcessRefundRequestJob;
use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing refund submission — copy to app/Http/Controllers/RefundRequestController.php
 *
 * Flow: customer POSTs → DB record → queued job → agent triages → customer notified.
 */
final class RefundRequestController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $refundRequest = RefundRequest::create([
            'user_id' => $request->user()->id,
            'order_id' => (int) $validated['order_id'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        ProcessRefundRequestJob::dispatch($refundRequest->id);

        return response()->json([
            'id' => $refundRequest->id,
            'status' => 'pending',
            'message' => 'Refund request received. We will review it shortly.',
        ], 202);
    }

    public function show(RefundRequest $refundRequest): JsonResponse
    {
        return response()->json([
            'id' => $refundRequest->id,
            'status' => $refundRequest->status,
            'agent_decision' => $refundRequest->agent_decision,
        ]);
    }
}
