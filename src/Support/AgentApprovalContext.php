<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\Laravel\ToolRoutingAgentManager;

/**
 * Human-readable snapshot when an agent pauses on a high-risk tool (HITL).
 *
 * Store {@see toStorage()} on your domain model (RefundRequest, etc.).
 * Show {@see toReviewPayload()} in the admin UI.
 * Call {@see resume()} when the admin approves or denies.
 */
final class AgentApprovalContext
{
    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $pendingApproval
     * @param array<int, array<string, mixed>> $toolCalls
     * @param array<int, array<string, mixed>> $decisions
     */
    public function __construct(
        public readonly array $state,
        public readonly string $approvalToken,
        public readonly array $pendingApproval,
        public readonly string $summary,
        public readonly string $recommendedAction,
        public readonly array $toolCalls,
        public readonly array $decisions,
    ) {
    }

    /** @param array<string, mixed> $result Agent chat() / chatInSession() response */
    public static function fromAgentResult(array $result): ?self
    {
        if (($result['stop_reason'] ?? '') !== 'awaiting_approval') {
            return null;
        }

        $pending = is_array($result['pending_approval'] ?? null) ? $result['pending_approval'] : [];
        $token = (string) ($result['approval_token'] ?? '');
        $state = is_array($result['state'] ?? null) ? $result['state'] : [];

        if ($token === '' || $state === []) {
            return null;
        }

        $toolCalls = is_array($result['tool_calls'] ?? null) ? $result['tool_calls'] : [];
        $decisions = is_array($result['decisions'] ?? null) ? $result['decisions'] : [];

        return new self(
            state: $state,
            approvalToken: $token,
            pendingApproval: $pending,
            summary: self::buildSummary($result, $pending, $toolCalls),
            recommendedAction: self::buildRecommendedAction($pending),
            toolCalls: $toolCalls,
            decisions: $decisions,
        );
    }

    /** @return array<string, mixed> Persist on RefundRequest.agent_review_context (JSON column) */
    public function toStorage(): array
    {
        return [
            'state' => $this->state,
            'approval_token' => $this->approvalToken,
            'pending_approval' => $this->pendingApproval,
            'summary' => $this->summary,
            'recommended_action' => $this->recommendedAction,
            'tool_calls' => $this->toolCalls,
            'decisions' => $this->decisions,
        ];
    }

    /** @param array<string, mixed> $stored From DB (agent_review_context) */
    public static function fromStorage(array $stored): self
    {
        return new self(
            state: is_array($stored['state'] ?? null) ? $stored['state'] : [],
            approvalToken: (string) ($stored['approval_token'] ?? ''),
            pendingApproval: is_array($stored['pending_approval'] ?? null) ? $stored['pending_approval'] : [],
            summary: (string) ($stored['summary'] ?? ''),
            recommendedAction: (string) ($stored['recommended_action'] ?? ''),
            toolCalls: is_array($stored['tool_calls'] ?? null) ? $stored['tool_calls'] : [],
            decisions: is_array($stored['decisions'] ?? null) ? $stored['decisions'] : [],
        );
    }

    /** @return array<string, mixed> JSON for admin review screen */
    public function toReviewPayload(): array
    {
        return [
            'summary' => $this->summary,
            'recommended_action' => $this->recommendedAction,
            'pending_tool' => $this->pendingApproval['tool'] ?? null,
            'pending_input' => $this->pendingApproval['input'] ?? [],
            'risk_level' => $this->pendingApproval['risk_level'] ?? 'high',
            'tools_used' => array_map(
                static fn (array $call): array => [
                    'tool' => $call['name'] ?? '',
                    'input' => $call['input'] ?? [],
                    'output' => $call['output'] ?? '',
                ],
                $this->toolCalls
            ),
            'decision_chain' => array_map(
                static fn (array $d): string => (string) ($d['type'] ?? '?'),
                $this->decisions
            ),
        ];
    }

    /** @return array<string, mixed> Full agent response after resume */
    public function resume(ToolRoutingAgentManager $manager, bool $approved): array
    {
        return $manager->make()->resumeWithApproval(
            AgentState::fromArray($this->state),
            $approved,
            $this->approvalToken,
        );
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $pending
     * @param array<int, array<string, mixed>> $toolCalls
     */
    private static function buildSummary(array $result, array $pending, array $toolCalls): string
    {
        $tool = (string) ($pending['tool'] ?? 'unknown');
        $input = is_array($pending['input'] ?? null) ? $pending['input'] : [];
        $parts = [
            (string) ($result['answer'] ?? 'Agent paused for approval.'),
            sprintf('Investigation used %d tool call(s).', count($toolCalls)),
            sprintf('Recommended next step: run %s with %s.', $tool, json_encode($input, JSON_THROW_ON_ERROR)),
        ];

        return implode(' ', $parts);
    }

    /** @param array<string, mixed> $pending */
    private static function buildRecommendedAction(array $pending): string
    {
        $tool = (string) ($pending['tool'] ?? 'unknown');
        $input = is_array($pending['input'] ?? null) ? $pending['input'] : [];

        return match ($tool) {
            'refund_order' => sprintf(
                'Refund order #%s — reason: %s',
                $input['order_id'] ?? '?',
                $input['reason'] ?? '(none)'
            ),
            'ban_user' => sprintf(
                'Ban user #%s — reason: %s',
                $input['user_id'] ?? '?',
                $input['reason'] ?? '(none)'
            ),
            default => sprintf('Execute %s(%s)', $tool, json_encode($input, JSON_THROW_ON_ERROR)),
        };
    }
}
