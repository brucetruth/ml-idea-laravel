<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use ML\IDEA\Laravel\Facades\MlIdeaAgent;
use ML\IDEA\RAG\Agents\AgentStreamEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SSE streaming admin chat — copy to app/Http/Controllers/AdminAgentStreamController.php
 *
 * Route: Route::post('/admin/ai/stream', [AdminAgentStreamController::class, 'stream']);
 */
final class AdminAgentStreamController
{
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'session_id' => ['nullable', 'string', 'max:120'],
        ]);

        return Response::stream(function () use ($validated): void {
            $this->sendEvent('connected', ['ok' => true]);

            foreach (MlIdeaAgent::chatStream(
                $validated['message'],
                $validated['session_id'] ?? null,
            ) as $event) {
                if (!$event instanceof AgentStreamEvent) {
                    continue;
                }

                $this->sendEvent($event->type, $event->payload);

                if ($event->type === 'final') {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function sendEvent(string $type, array $data): void
    {
        echo 'event: ' . $type . "\n";
        echo 'data: ' . json_encode(['type' => $type, ...$data], JSON_THROW_ON_ERROR) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
