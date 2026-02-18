<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->fixProjectTaskAssignedIds();
        $this->fixProjectTaskAssignmentIds();
    }

    public function down(): void
    {
        // Irreversible data correction.
    }

    private function fixProjectTaskAssignedIds(): void
    {
        if (! Schema::hasTable('project_tasks')
            || ! Schema::hasTable('employees')
            || ! Schema::hasColumn('project_tasks', 'assigned_type')
            || ! Schema::hasColumn('project_tasks', 'assigned_id')) {
            return;
        }

        $rows = DB::table('project_tasks as task')
            ->join('employees as employee_by_user', 'employee_by_user.user_id', '=', 'task.assigned_id')
            ->leftJoin('employees as employee_by_id', 'employee_by_id.id', '=', 'task.assigned_id')
            ->where('task.assigned_type', 'employee')
            ->whereNotNull('task.assigned_id')
            ->whereNull('employee_by_id.id')
            ->select([
                'task.id as task_id',
                'employee_by_user.id as employee_id',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('project_tasks')
                ->where('id', $row->task_id)
                ->update(['assigned_id' => (int) $row->employee_id]);
        }
    }

    private function fixProjectTaskAssignmentIds(): void
    {
        if (! Schema::hasTable('project_task_assignments')
            || ! Schema::hasTable('employees')
            || ! Schema::hasColumn('project_task_assignments', 'assignee_type')
            || ! Schema::hasColumn('project_task_assignments', 'assignee_id')) {
            return;
        }

        $rows = DB::table('project_task_assignments as assignment')
            ->join('employees as employee_by_user', 'employee_by_user.user_id', '=', 'assignment.assignee_id')
            ->leftJoin('employees as employee_by_id', 'employee_by_id.id', '=', 'assignment.assignee_id')
            ->where('assignment.assignee_type', 'employee')
            ->whereNotNull('assignment.assignee_id')
            ->whereNull('employee_by_id.id')
            ->select([
                'assignment.id as assignment_id',
                'employee_by_user.id as employee_id',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('project_task_assignments')
                ->where('id', $row->assignment_id)
                ->update(['assignee_id' => (int) $row->employee_id]);
        }
    }
};

