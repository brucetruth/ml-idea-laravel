<?php

declare(strict_types=1);

use App\Http\Controllers\AdminRefundApprovalController;
use Illuminate\Support\Facades\Route;

/*
| Refund approval routes — merge into routes/api.php
|
| GET  /admin/refunds/{refundRequest}/review  → agent summary + investigation
| POST /admin/refunds/{refundRequest}/decide    → { "approved": true, "admin_note": "..." }
*/

Route::middleware(['auth:sanctum', 'can:review-refunds'])
    ->prefix('admin/refunds')
    ->group(function (): void {
        Route::get('/{refundRequest}/review', [AdminRefundApprovalController::class, 'review']);
        Route::post('/{refundRequest}/decide', [AdminRefundApprovalController::class, 'decide']);
    });
