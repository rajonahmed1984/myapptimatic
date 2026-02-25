<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('author_type', 20);
            $table->unsignedBigInteger('author_id');
            $table->text('message')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('project_messages')->nullOnDelete();
            $table->json('reactions')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->string('pinned_by_type', 20)->nullable();
            $table->unsignedBigInteger('pinned_by_id')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamps();

            $table->index(['author_type', 'author_id']);
            $table->index(['project_id', 'is_pinned']);
            $table->index(['pinned_by_type', 'pinned_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_messages');
    }
};
