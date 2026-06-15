<?php

declare(strict_types=1);

/**
 * Migration for agent run audit log.
 *
 *   php artisan make:migration create_agent_runs_table
 *   # paste this up()/down() body
 *
 * Then enable DB logging:
 *   MLIDEA_LOGGING_DRIVER=database
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->timestamp('logged_at');
            $table->string('agent_name');
            $table->string('session_id', 120)->nullable()->index();
            $table->text('user_message')->nullable();
            $table->boolean('resume')->default(false);
            $table->text('answer');
            $table->string('stop_reason', 64)->index();
            $table->unsignedSmallInteger('iterations')->default(0);
            $table->json('tool_calls');
            $table->json('decisions');
            $table->json('usage');
            $table->json('budget');
            $table->json('telemetry')->nullable();
            $table->json('pending_approval')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
