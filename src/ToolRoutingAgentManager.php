<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel;

use ML\IDEA\RAG\Agents\AgentBudget;
use ML\IDEA\RAG\Agents\AgentContextManager;
use ML\IDEA\RAG\Agents\AgentHandoffRegistry;
use ML\IDEA\RAG\Agents\AgentPolicy;
use ML\IDEA\RAG\Agents\AgentStateStoreFactory;
use ML\IDEA\RAG\Agents\AgentStreamEvent;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\AgentStateStoreInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\Laravel\Events\AgentAwaitingApproval;
use ML\IDEA\Laravel\Events\AgentRunCompleted;
use ML\IDEA\Laravel\Support\AgentMemoryStoreFactory;
use ML\IDEA\Laravel\Support\AgentRunLoggerFactory;
use ML\IDEA\Laravel\Support\AgentTracerFactory;
use ML\IDEA\Laravel\Support\EpisodicMemorySummarizerFactory;
use ML\IDEA\Laravel\Support\RoutingModelFactory;
use ML\IDEA\Laravel\Support\ToolCircuitBreakerFactory;
use ML\IDEA\Laravel\Support\ToolIdempotencyStoreFactory;

final class ToolRoutingAgentManager
{
    /** @var array<string, ToolRoutingAgent> */
    private array $handoffAgents = [];

    /** @var array<string, string> */
    private array $handoffDescriptions = [];

    /** @var array<string, ToolInterface> */
    private array $registeredTools = [];

    /**
     * @param callable(class-string): object $resolver
     * @param array<string, mixed> $config
     * @param mixed $database Illuminate db service when logging driver is database
     * @param mixed $eventDispatcher Illuminate\Contracts\Events\Dispatcher|null
     * @param mixed $logger PSR-3 logger for psr3 logging driver
     */
    public function __construct(
        private readonly mixed $resolver,
        private array $config,
        private readonly mixed $database = null,
        private readonly mixed $eventDispatcher = null,
        private readonly mixed $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function make(array $overrides = []): ToolRoutingAgent
    {
        $config = $this->mergeConfig($overrides);
        $agentConfig = is_array($config['agent'] ?? null) ? $config['agent'] : [];
        $budgetConfig = is_array($config['budget'] ?? null) ? $config['budget'] : [];
        $storeConfig = is_array($config['store'] ?? null) ? $config['store'] : [];
        $contextConfig = is_array($config['context'] ?? null) ? $config['context'] : [];
        $tracingConfig = is_array($config['tracing'] ?? null) ? $config['tracing'] : [];
        $loggingConfig = is_array($config['logging'] ?? null) ? $config['logging'] : [];
        $loggingPath = isset($config['logging_path']) && is_string($config['logging_path'])
            ? $config['logging_path']
            : null;
        $idempotencyConfig = is_array($config['idempotency'] ?? null) ? $config['idempotency'] : [];
        $memoryConfig = is_array($config['memory'] ?? null) ? $config['memory'] : [];
        $circuitConfig = is_array($config['circuit_breaker'] ?? null) ? $config['circuit_breaker'] : [];
        $parallelConfig = is_array($config['parallel_tools'] ?? null) ? $config['parallel_tools'] : [];
        $idempotencyPath = isset($config['idempotency_path']) && is_string($config['idempotency_path'])
            ? $config['idempotency_path']
            : null;
        $memoryPath = isset($config['memory_path']) && is_string($config['memory_path'])
            ? $config['memory_path']
            : null;

        $policy = new AgentPolicy(
            maxToolCalls: (int) ($agentConfig['max_tool_calls'] ?? 16),
            pauseForApproval: (bool) ($agentConfig['pause_for_approval'] ?? false),
        );

        $contextManager = null;
        if (($contextConfig['enabled'] ?? true) === true) {
            $contextManager = new AgentContextManager(
                maxRoutingMessages: (int) ($contextConfig['max_messages'] ?? 24),
                maxToolMessageChars: (int) ($contextConfig['max_tool_output_chars'] ?? 4000),
            );
        }

        $handoffRegistry = $this->buildHandoffRegistry();
        $idempotencyStore = ToolIdempotencyStoreFactory::make($idempotencyConfig, $idempotencyPath);
        $summarizer = EpisodicMemorySummarizerFactory::make($memoryConfig);
        $memoryStore = AgentMemoryStoreFactory::make($memoryConfig, $memoryPath, $summarizer);
        $circuitBreaker = ToolCircuitBreakerFactory::make($circuitConfig);
        $parallelAutoload = isset($parallelConfig['autoload']) && is_string($parallelConfig['autoload'])
            ? $parallelConfig['autoload']
            : null;

        return new ToolRoutingAgent(
            RoutingModelFactory::make(is_array($config['model'] ?? null) ? $config['model'] : []),
            $this->resolveTools(is_array($config['tools'] ?? null) ? $config['tools'] : []),
            maxIterations: (int) ($agentConfig['max_iterations'] ?? 8),
            agentName: (string) ($agentConfig['name'] ?? 'LaravelAgent'),
            agentFeatures: is_array($agentConfig['features'] ?? null) ? $agentConfig['features'] : [],
            systemPrompt: isset($agentConfig['system_prompt']) ? (string) $agentConfig['system_prompt'] : null,
            toolExecutor: new ToolExecutor(
                policy: $policy,
                idempotencyStore: $idempotencyStore,
                circuitBreaker: $circuitBreaker,
            ),
            budget: new AgentBudget(
                (int) ($agentConfig['max_iterations'] ?? 8),
                (int) ($agentConfig['max_tool_calls'] ?? 16),
                (int) ($budgetConfig['max_runtime_ms'] ?? 30000),
                (int) ($budgetConfig['max_tokens'] ?? 0),
                (float) ($budgetConfig['max_estimated_cost'] ?? 0.0),
            ),
            contextManager: $contextManager,
            includePlanningPrompt: (bool) ($agentConfig['include_planning_prompt'] ?? true),
            stateStore: $this->buildStateStore($storeConfig),
            handoffRegistry: $handoffRegistry,
            agentTracer: AgentTracerFactory::make($tracingConfig),
            agentRunLogger: AgentRunLoggerFactory::make($loggingConfig, $loggingPath, $this->database, $this->logger),
            memoryStore: $memoryStore,
            orderToolCallsByRisk: (bool) ($agentConfig['order_tool_calls_by_risk'] ?? true),
            parallelToolCalls: (bool) ($parallelConfig['enabled'] ?? false),
            parallelAutoloadPath: $parallelAutoload,
        );
    }

    /** @return array<string, mixed> */
    public function chat(string $message, ?string $sessionId = null): array
    {
        $agent = $this->make();

        $result = ($sessionId === null || $sessionId === '')
            ? $agent->chat($message)
            : $agent->chatInSession($sessionId, $message);

        $this->dispatchAgentEvents($result, $sessionId);

        return $result;
    }

    /** @return \Generator<int, AgentStreamEvent, mixed, array<string, mixed>> */
    public function chatStream(string $message, ?string $sessionId = null): \Generator
    {
        $agent = $this->make();

        $generator = ($sessionId === null || $sessionId === '')
            ? $agent->chatStream($message)
            : $agent->chatStreamInSession($sessionId, $message);

        while ($generator->valid()) {
            yield $generator->current();
            $generator->next();
        }

        $result = $generator->getReturn();
        if (is_array($result)) {
            $this->dispatchAgentEvents($result, $sessionId);
        }

        return is_array($result) ? $result : [];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function dispatchAgentEvents(array $result, ?string $sessionId): void
    {
        $eventsConfig = is_array($this->config['events'] ?? null) ? $this->config['events'] : [];
        if (($eventsConfig['enabled'] ?? true) !== true || $this->eventDispatcher === null) {
            return;
        }

        if (!is_object($this->eventDispatcher) || !method_exists($this->eventDispatcher, 'dispatch')) {
            return;
        }

        $this->eventDispatcher->dispatch(new AgentRunCompleted($result, $sessionId));

        if (($result['stop_reason'] ?? '') !== 'awaiting_approval') {
            return;
        }

        $context = $this->approvalContextFromResult($result);
        if ($context !== null) {
            $this->eventDispatcher->dispatch(new AgentAwaitingApproval($context, $sessionId));
        }
    }

    public function registerHandoff(string $name, ToolRoutingAgent $agent, string $description = ''): self
    {
        $this->handoffAgents[trim($name)] = $agent;
        $this->handoffDescriptions[trim($name)] = trim($description);

        return $this;
    }

    public function registerTool(ToolInterface $tool): self
    {
        $this->registeredTools[$tool->name()] = $tool;

        return $this;
    }

    public function registerToolClass(string $toolClass): self
    {
        $tool = ($this->resolver)($toolClass);
        if (!$tool instanceof ToolInterface) {
            throw new \InvalidArgumentException(sprintf('Tool %s must implement ToolInterface.', $toolClass));
        }

        return $this->registerTool($tool);
    }

    /** @return array<int, string> */
    public function toolNames(): array
    {
        $names = [];
        foreach (is_array($this->config['tools'] ?? null) ? $this->config['tools'] : [] as $toolClass) {
            $tool = ($this->resolver)((string) $toolClass);
            if ($tool instanceof ToolInterface) {
                $names[] = $tool->name();
            }
        }

        foreach ($this->registeredTools as $tool) {
            $names[] = $tool->name();
        }

        return array_values(array_unique($names));
    }

    /**
     * Build review context when chat() stops with awaiting_approval.
     *
     * @param array<string, mixed> $result
     */
    public function approvalContextFromResult(array $result): ?\ML\IDEA\Laravel\Support\AgentApprovalContext
    {
        return \ML\IDEA\Laravel\Support\AgentApprovalContext::fromAgentResult($result);
    }

    public function resumeWithApproval(
        \ML\IDEA\RAG\Agents\AgentState $state,
        bool $approved,
        string $approvalToken,
        ?string $sessionId = null,
    ): array {
        $result = $this->make()->resumeWithApproval($state, $approved, $approvalToken);
        $this->dispatchAgentEvents($result, $sessionId);

        return $result;
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function mergeConfig(array $overrides): array
    {
        return array_replace_recursive($this->config, $overrides);
    }

    private function buildHandoffRegistry(): ?AgentHandoffRegistry
    {
        if ($this->handoffAgents === []) {
            return null;
        }

        $registry = new AgentHandoffRegistry();
        foreach ($this->handoffAgents as $name => $agent) {
            $registry->register($name, $agent, $this->handoffDescriptions[$name] ?? '');
        }

        return $registry;
    }

    /** @param array<int, class-string> $toolClasses @return array<int, ToolInterface> */
    private function resolveTools(array $toolClasses): array
    {
        /** @var array<string, ToolInterface> $byName */
        $byName = [];

        foreach ($toolClasses as $toolClass) {
            $tool = ($this->resolver)($toolClass);
            if (!$tool instanceof ToolInterface) {
                throw new \InvalidArgumentException(sprintf('Tool %s must implement ToolInterface.', $toolClass));
            }
            $byName[$tool->name()] = $tool;
        }

        foreach ($this->registeredTools as $tool) {
            $byName[$tool->name()] = $tool;
        }

        return array_values($byName);
    }

    /** @param array<string, mixed> $storeConfig */
    private function buildStateStore(array $storeConfig): ?AgentStateStoreInterface
    {
        $driver = (string) ($storeConfig['driver'] ?? 'auto');
        if ($driver === 'none') {
            return null;
        }

        return AgentStateStoreFactory::create($storeConfig);
    }
}
