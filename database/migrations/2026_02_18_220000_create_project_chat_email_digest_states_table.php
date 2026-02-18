<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_chat_email_digest_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_email');
            $table->unsignedBigInteger('last_notified_message_id')->default(0);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'recipient_email'], 'project_chat_digest_unique');
            $table->index(['project_id', 'last_notified_message_id'], 'project_chat_digest_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_chat_email_digest_states');
    }
};
