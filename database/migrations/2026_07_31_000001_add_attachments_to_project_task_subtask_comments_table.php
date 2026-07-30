<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_task_subtask_comments')) {
            Schema::table('project_task_subtask_comments', function (Blueprint $table) {
                if (! Schema::hasColumn('project_task_subtask_comments', 'attachment_path')) {
                    $table->string('attachment_path')->nullable()->after('message');
                }
                if (! Schema::hasColumn('project_task_subtask_comments', 'attachment_paths')) {
                    $table->json('attachment_paths')->nullable()->after('attachment_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_task_subtask_comments')) {
            Schema::table('project_task_subtask_comments', function (Blueprint $table) {
                if (Schema::hasColumn('project_task_subtask_comments', 'attachment_paths')) {
                    $table->dropColumn('attachment_paths');
                }
                if (Schema::hasColumn('project_task_subtask_comments', 'attachment_path')) {
                    $table->dropColumn('attachment_path');
                }
            });
        }
    }
};
