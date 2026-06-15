<?php

declare(strict_types=1);

use App\Http\Controllers\AdminAgentController;
use Illuminate\Support\Facades\Route;

/*
| Copy into routes/api.php (protect with auth + admin middleware in production)
*/

Route::middleware(['auth:sanctum', 'can:access-admin-ai'])
    ->prefix('admin/ai')
    ->group(function (): void {
        Route::post('/chat', [AdminAgentController::class, 'chat']);
        Route::post('/approve', [AdminAgentController::class, 'approve']);
    });
