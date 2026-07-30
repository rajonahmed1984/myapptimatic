<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_tasks')) {
            Schema::table('project_tasks', function (Blueprint $table) {
                if (! Schema::hasColumn('project_tasks', 'attachment_paths')) {
                    $table->json('attachment_paths')->nullable()->after('tags');
                }
            });
        }

        if (Schema::hasTable('project_task_subtasks')) {
            Schema::table('project_task_subtasks', function (Blueprint $table) {
                if (! Schema::hasColumn('project_task_subtasks', 'attachment_paths')) {
                    $table->json('attachment_paths')->nullable()->after('attachment_path');
                }
            });

            // Backfill existing single attachment_path into attachment_paths array
            $existingSubtasks = DB::table('project_task_subtasks')
                ->whereNotNull('attachment_path')
                ->get(['id', 'attachment_path']);

            foreach ($existingSubtasks as $subtask) {
                DB::table('project_task_subtasks')
                    ->where('id', $subtask->id)
                    ->update([
                        'attachment_paths' => json_encode([$subtask->attachment_path]),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_tasks') && Schema::hasColumn('project_tasks', 'attachment_paths')) {
            Schema::table('project_tasks', function (Blueprint $table) {
                $table->dropColumn('attachment_paths');
            });
        }

        if (Schema::hasTable('project_task_subtasks') && Schema::hasColumn('project_task_subtasks', 'attachment_paths')) {
            Schema::table('project_task_subtasks', function (Blueprint $table) {
                $table->dropColumn('attachment_paths');
            });
        }
    }
};
