<?php

declare(strict_types=1);

/**
 * Merge into config/mlidea.php after publishing ml-idea config.
 */
return [
    'model' => [
        'driver' => env('MLIDEA_MODEL_DRIVER', 'openai'),
    ],

    'agent' => [
        'name' => env('MLIDEA_AGENT_NAME', 'AiAdmin'),
        'system_prompt' => <<<'PROMPT'
You are an AI admin assistant for this application.
Use tools to inspect users and orders before mutating data.
Explain high-risk actions clearly before executing them.
PROMPT,
        'pause_for_approval' => env('MLIDEA_PAUSE_FOR_APPROVAL', true),
        'max_iterations' => (int) env('MLIDEA_MAX_ITERATIONS', 8),
    ],

    'tools' => [
        \App\AiAdmin\Tools\ListUsersTool::class,
        \App\AiAdmin\Tools\GetUserTool::class,
        \App\AiAdmin\Tools\UpdateUserRoleTool::class,
        \App\AiAdmin\Tools\BanUserTool::class,
        \App\AiAdmin\Tools\ListOrdersTool::class,
        \App\AiAdmin\Tools\RefundOrderTool::class,
    ],
];
