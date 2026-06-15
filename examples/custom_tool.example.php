<?php

declare(strict_types=1);

/**
 * Copy into app/Tools/NotifyTeamTool.php (or any namespace).
 *
 * Requirements:
 * - composer require brucetruth/ml-idea-laravel
 * - add class to config/mlidea.php `tools` array OR MlIdeaAgent::registerToolClass()
 */

namespace App\Tools;

use App\Services\SlackNotifier;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class NotifyTeamTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly SlackNotifier $slack)
    {
    }

    public function name(): string
    {
        return 'notify_team';
    }

    public function description(): string
    {
        return 'Send a message to the on-call Slack channel.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['channel', 'message'],
            'properties' => [
                'channel' => ['type' => 'string', 'minLength' => 1],
                'message' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000],
            ],
        ];
    }

    public function examples(): array
    {
        return [
            ['channel' => '#ops', 'message' => 'Deployment finished successfully.'],
        ];
    }

    public function riskLevel(): string
    {
        return 'medium';
    }

    public function invoke(array $input): string
    {
        $channel = (string) ($input['channel'] ?? '');
        $message = (string) ($input['message'] ?? '');

        $this->slack->send($channel, $message);

        return json_encode(['ok' => true, 'channel' => $channel], JSON_THROW_ON_ERROR);
    }
}
