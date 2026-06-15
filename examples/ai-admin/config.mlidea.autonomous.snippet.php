<?php

/**
 * Autonomous AI admin — merge into config/mlidea.php for jobs that need no HITL.
 *
 * Key idea: pause_for_approval only pauses on `high` risk. Low/medium tools
 * (add_user_note, tag_order, update_support_ticket_status, update_user_role)
 * execute immediately and write to your DB inside the tool class.
 *
 * Do NOT register ban_user / refund_order for autonomous agents.
 */
return [
    'pause_for_approval' => true,

    // Allow-list only — do not register ban_user / refund_order for autonomous agents
    'tools' => [
        \App\AiAdmin\Tools\ListUsersTool::class,
        \App\AiAdmin\Tools\GetUserTool::class,
        \App\AiAdmin\Tools\ListOrdersTool::class,
        \App\AiAdmin\Tools\AddUserNoteTool::class,
        \App\AiAdmin\Tools\TagOrderTool::class,
        \App\AiAdmin\Tools\UpdateSupportTicketStatusTool::class,
        \App\AiAdmin\Tools\UpdateUserRoleTool::class,
    ],
];
