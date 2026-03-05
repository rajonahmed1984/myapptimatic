<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_account_sessions')) {
            return;
        }

        Schema::create('mail_account_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('assignee_type', 20);
            $table->unsignedBigInteger('assignee_id');
            $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->char('session_token_hash', 64)->unique();
            $table->boolean('remember')->default(false);
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['assignee_type', 'assignee_id'], 'mail_session_assignee_index');
            $table->index(['mail_account_id'], 'mail_session_account_index');
            $table->index(['invalidated_at'], 'mail_session_invalidated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_account_sessions');
    }
};
