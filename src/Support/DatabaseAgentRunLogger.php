<?php

declare(strict_types=1);

namespace ML\IDEA\Laravel\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use ML\IDEA\RAG\Agents\AgentRunLogEntry;
use ML\IDEA\RAG\Contracts\AgentRunLoggerInterface;

final class DatabaseAgentRunLogger implements AgentRunLoggerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = 'agent_runs',
    ) {
    }

    public function log(AgentRunLogEntry $entry): void
    {
        $row = $entry->toArray();
        $loggedAt = Carbon::parse((string) $row['logged_at'])->format('Y-m-d H:i:s');

        $this->connection->table($this->table)->insert([
            'id' => $row['id'],
            'logged_at' => $loggedAt,
            'agent_name' => $row['agent_name'],
            'session_id' => $row['session_id'],
            'user_message' => $row['user_message'],
            'resume' => (bool) $row['resume'],
            'answer' => $row['answer'],
            'stop_reason' => $row['stop_reason'],
            'iterations' => (int) $row['iterations'],
            'tool_calls' => json_encode($row['tool_calls'], JSON_THROW_ON_ERROR),
            'decisions' => json_encode($row['decisions'], JSON_THROW_ON_ERROR),
            'usage' => json_encode($row['usage'], JSON_THROW_ON_ERROR),
            'budget' => json_encode($row['budget'], JSON_THROW_ON_ERROR),
            'telemetry' => $row['telemetry'] !== null
                ? json_encode($row['telemetry'], JSON_THROW_ON_ERROR)
                : null,
            'pending_approval' => $row['pending_approval'] !== null
                ? json_encode($row['pending_approval'], JSON_THROW_ON_ERROR)
                : null,
            'created_at' => $loggedAt,
            'updated_at' => $loggedAt,
        ]);
    }

    public static function connectionFrom(mixed $database, ?string $connectionName = null): Connection
    {
        if ($database instanceof Connection) {
            return $database;
        }

        if ($database instanceof DatabaseManager) {
            return $connectionName !== null && $connectionName !== ''
                ? $database->connection($connectionName)
                : $database->connection();
        }

        throw new \InvalidArgumentException(
            'Database agent run logging requires an Illuminate\Database\Connection or DatabaseManager.',
        );
    }
}
