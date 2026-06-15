<?php

declare(strict_types=1);

namespace App\Providers;

use App\AiAdmin\Support\AdminStore;
use App\AiAdmin\Tools\AddUserNoteTool;
use App\AiAdmin\Tools\BanUserTool;
use App\AiAdmin\Tools\GetUserTool;
use App\AiAdmin\Tools\ListOrdersTool;
use App\AiAdmin\Tools\ListUsersTool;
use App\AiAdmin\Tools\RefundOrderTool;
use App\AiAdmin\Tools\TagOrderTool;
use App\AiAdmin\Tools\UpdateSupportTicketStatusTool;
use App\AiAdmin\Tools\UpdateUserRoleTool;
use Illuminate\Support\ServiceProvider;
use ML\IDEA\Laravel\Facades\MlIdeaAgent;

/**
 * Snippet for AppServiceProvider — merge into register() and boot().
 */
final class AiAdminAgentToolsProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminStore::class);
    }

    public function boot(): void
    {
        foreach ([
            ListUsersTool::class,
            GetUserTool::class,
            UpdateUserRoleTool::class,
            BanUserTool::class,
            ListOrdersTool::class,
            RefundOrderTool::class,
            AddUserNoteTool::class,
            TagOrderTool::class,
            UpdateSupportTicketStatusTool::class,
        ] as $toolClass) {
            MlIdeaAgent::registerToolClass($toolClass);
        }
    }
}
