<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('assignee_type', 20);
            $table->unsignedBigInteger('assignee_id');
            $table->timestamps();

            $table->unique(['project_task_id', 'assignee_type', 'assignee_id'], 'task_assignment_unique');
            $table->index(['assignee_type', 'assignee_id'], 'task_assignment_assignee_index');
        });

        if (Schema::hasColumn('project_tasks', 'assigned_type') && Schema::hasColumn('project_tasks', 'assigned_id')) {
            $tasks = DB::table('project_tasks')
                ->select('id', 'assigned_type', 'assigned_id')
                ->whereNotNull('assigned_type')
                ->whereNotNull('assigned_id')
                ->get();

            foreach ($tasks as $task) {
                DB::table('project_task_assignments')->insertOrIgnore([
                    'project_task_id' => $task->id,
                    'assignee_type' => $task->assigned_type,
                    'assignee_id' => $task->assigned_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_assignments');
    }
};
