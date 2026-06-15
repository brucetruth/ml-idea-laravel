<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Events;

use ML\IDEA\Laravel\Support\AgentApprovalContext;

/**
 * Fired when an agent pauses on a high-risk tool (stop_reason: awaiting_approval).
 */
final class AgentAwaitingApproval
{
    public function __construct(
        public readonly AgentApprovalContext $context,
        public readonly ?string $sessionId = null,
    ) {
    }
}
