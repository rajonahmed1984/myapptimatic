<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_task_subtask_comments')) {
            Schema::create('project_task_subtask_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
                $table->foreignId('project_task_subtask_id')->constrained('project_task_subtasks')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('project_task_subtask_comments')->cascadeOnDelete();
                $table->string('actor_type', 20);
                $table->unsignedBigInteger('actor_id');
                $table->text('message');
                $table->timestamps();

                $table->index(['project_task_subtask_id', 'parent_id'], 'ptsc_subtask_parent_idx');
                $table->index(['project_task_id', 'created_at'], 'ptsc_task_created_idx');
                $table->index(['actor_type', 'actor_id'], 'ptsc_actor_idx');
            });

            return;
        }

        Schema::table('project_task_subtask_comments', function (Blueprint $table) {
            if (! Schema::hasIndex('project_task_subtask_comments', 'ptsc_subtask_parent_idx')) {
                $table->index(['project_task_subtask_id', 'parent_id'], 'ptsc_subtask_parent_idx');
            }
            if (! Schema::hasIndex('project_task_subtask_comments', 'ptsc_task_created_idx')) {
                $table->index(['project_task_id', 'created_at'], 'ptsc_task_created_idx');
            }
            if (! Schema::hasIndex('project_task_subtask_comments', 'ptsc_actor_idx')) {
                $table->index(['actor_type', 'actor_id'], 'ptsc_actor_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_subtask_comments');
    }
};
