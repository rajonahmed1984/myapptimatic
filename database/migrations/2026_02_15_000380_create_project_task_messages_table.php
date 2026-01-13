<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_task_id');
            $table->string('author_type', 20);
            $table->unsignedBigInteger('author_id');
            $table->text('message')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->index(['author_type', 'author_id']);
            
            $table->foreign('project_task_id')->references('id')->on('project_tasks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_messages');
    }
};
