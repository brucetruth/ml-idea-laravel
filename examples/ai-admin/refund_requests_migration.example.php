<?php

declare(strict_types=1);

/**
 * Example migration columns for refund_requests table.
 *
 *   php artisan make:migration add_agent_review_to_refund_requests
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table): void {
            $table->string('status')->default('pending');
            $table->text('agent_decision')->nullable();
            $table->json('agent_review_context')->nullable(); // AgentApprovalContext::toStorage()
            $table->text('admin_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table): void {
            $table->dropColumn(['status', 'agent_decision', 'agent_review_context', 'admin_note']);
        });
    }
};
