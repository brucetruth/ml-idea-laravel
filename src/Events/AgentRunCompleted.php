<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Events;

/**
 * Fired after every completed agent run (chat or resume).
 *
 * Listen to notify Slack, update dashboards, or trigger follow-up jobs.
 */
final class AgentRunCompleted
{
    /**
     * @param array<string, mixed> $result ToolRoutingAgent chat()/resume response
     */
    public function __construct(
        public readonly array $result,
        public readonly ?string $sessionId = null,
    ) {
    }
}
