<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['project_task_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_subtasks');
    }
};
