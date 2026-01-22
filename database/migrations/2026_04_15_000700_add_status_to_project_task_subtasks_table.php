<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_task_subtasks', function (Blueprint $table) {
            $table->string('status', 20)->default('open')->after('is_completed');
        });

        DB::table('project_task_subtasks')
            ->where('is_completed', true)
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('project_task_subtasks', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
