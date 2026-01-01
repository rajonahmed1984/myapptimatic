<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('status_audit_logs')) {
            return;
        }

        Schema::create('status_audit_logs', function (Blueprint $table) {
            $table->id();
            // limit length to keep composite index within MySQL key size limits
            $table->string('model_type', 191); // e.g., 'Invoice', 'Subscription', 'License', 'Customer'
            $table->unsignedBigInteger('model_id');
            $table->string('old_status')->nullable();
            $table->string('new_status', 64);
            $table->string('reason')->nullable(); // e.g., 'auto_overdue', 'manual_approval', 'payment_received'
            $table->unsignedBigInteger('triggered_by')->nullable(); // Admin user ID if manual
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();
            
            $table->index(['model_type', 'model_id']);
            $table->index('new_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_audit_logs');
    }
};
