<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ML\IDEA\Laravel\Facades\MlIdeaAgent;
use ML\IDEA\RAG\Agents\AgentState;

/**
 * Example controller — copy to app/Http/Controllers/AdminAgentController.php
 */
final class AdminAgentController
{
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'session_id' => ['nullable', 'string', 'max:120'],
        ]);

        $result = MlIdeaAgent::chat(
            $validated['message'],
            $validated['session_id'] ?? null,
        );

        return response()->json([
            'answer' => $result['answer'],
            'stop_reason' => $result['stop_reason'],
            'session_id' => $result['session_id'] ?? $validated['session_id'] ?? null,
            'tool_calls' => $result['tool_calls'] ?? [],
            'decisions' => $result['decisions'] ?? [],
            'telemetry' => $result['telemetry'] ?? null,
            'pending_approval' => $result['pending_approval'] ?? null,
            'approval_token' => $result['approval_token'] ?? null,
            'state' => ($result['stop_reason'] ?? '') === 'awaiting_approval' ? ($result['state'] ?? null) : null,
        ]);
    }

    public function approve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'state' => ['required', 'array'],
            'approval_token' => ['required', 'string'],
            'approved' => ['required', 'boolean'],
            'session_id' => ['nullable', 'string', 'max:120'],
        ]);

        $agent = MlIdeaAgent::make();
        $result = $agent->resumeWithApproval(
            AgentState::fromArray($validated['state']),
            (bool) $validated['approved'],
            $validated['approval_token'],
        );

        if (!empty($validated['session_id'])) {
            $result['session_id'] = $validated['session_id'];
        }

        return response()->json($result);
    }
}
