<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeTypeValue('project_tasks', 'assigned_type');
        $this->normalizeTypeValue('project_task_assignments', 'assignee_type');
        $this->normalizeTypeValue('project_task_messages', 'author_type');
        $this->normalizeTypeValue('project_task_activities', 'actor_type');
    }

    public function down(): void
    {
        $this->denormalizeTypeValue('project_tasks', 'assigned_type');
        $this->denormalizeTypeValue('project_task_assignments', 'assignee_type');
        $this->denormalizeTypeValue('project_task_messages', 'author_type');
        $this->denormalizeTypeValue('project_task_activities', 'actor_type');
    }

    private function normalizeTypeValue(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, 'salesrep')
            ->update([$column => 'sales_rep']);
    }

    private function denormalizeTypeValue(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, 'sales_rep')
            ->update([$column => 'salesrep']);
    }
};
