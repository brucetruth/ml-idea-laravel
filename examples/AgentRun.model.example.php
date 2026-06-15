<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Copy to app/Models/AgentRun.php — optional query helper for MLIDEA_LOGGING_DRIVER=database
 *
 * @property string $id
 * @property string $logged_at
 * @property string $agent_name
 * @property string|null $session_id
 * @property string|null $user_message
 * @property bool $resume
 * @property string $answer
 * @property string $stop_reason
 * @property int $iterations
 * @property array<int, array<string, mixed>> $tool_calls
 * @property array<int, array<string, mixed>> $decisions
 * @property array<string, mixed> $usage
 * @property array<string, mixed> $budget
 * @property array<string, mixed>|null $telemetry
 * @property array<string, mixed>|null $pending_approval
 */
final class AgentRun extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'agent_runs';

    protected $fillable = [
        'id',
        'logged_at',
        'agent_name',
        'session_id',
        'user_message',
        'resume',
        'answer',
        'stop_reason',
        'iterations',
        'tool_calls',
        'decisions',
        'usage',
        'budget',
        'telemetry',
        'pending_approval',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'resume' => 'boolean',
            'iterations' => 'integer',
            'tool_calls' => 'array',
            'decisions' => 'array',
            'usage' => 'array',
            'budget' => 'array',
            'telemetry' => 'array',
            'pending_approval' => 'array',
        ];
    }
}
