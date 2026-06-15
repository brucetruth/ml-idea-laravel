<?php

declare(strict_types=1);

/**
 * Register custom tools in AppServiceProvider.
 */

namespace App\Providers;

use App\Tools\NotifyTeamTool;
use Illuminate\Support\ServiceProvider;
use ML\IDEA\Laravel\Facades\MlIdeaAgent;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Resolve from container (supports DI)
        MlIdeaAgent::registerToolClass(NotifyTeamTool::class);

        // Or pass a built instance
        // MlIdeaAgent::registerTool($this->app->make(NotifyTeamTool::class));
    }
}

/*
 * config/mlidea.php alternative:

'tools' => [
    \App\Tools\NotifyTeamTool::class,
    \App\AiAdmin\Tools\ListUsersTool::class,
],

*/
