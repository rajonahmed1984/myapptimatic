<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $employee = $request->user()?->employee;
        $employeeId = $employee?->id;

        $projectRelation = $employeeId
            ? Project::query()->whereHas('employees', fn ($query) => $query->whereKey($employeeId))
            : Project::query()->whereRaw('0 = 1');

        $projectIds = (clone $projectRelation)->pluck('id');
        $totalProjects = $projectIds->count();

        $projectStatusCounts = (clone $projectRelation)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $recentProjects = (clone $projectRelation)
            ->with(['customer'])
            ->withCount('tasks')
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        $taskStatusCounts = $employeeId
            ? ProjectTask::query()
                ->select('status', DB::raw('COUNT(*) as total'))
                ->where('assigned_type', 'employee')
                ->where('assigned_id', $employeeId)
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        $taskStats = [
            'total' => (int) $taskStatusCounts->sum(),
            'in_progress' => (int) ($taskStatusCounts['in_progress'] ?? 0),
            'completed' => (int) (($taskStatusCounts['done'] ?? 0) + ($taskStatusCounts['completed'] ?? 0)),
        ];

        $contractSummary = null;
        $contractProjects = collect();

        if ($employeeId && $employee?->employment_type === 'contract') {
            $contractProjectsQuery = Project::query()
                ->whereHas('employees', fn ($query) => $query->whereKey($employeeId))
                ->whereNotNull('contract_employee_total_earned');

            $contractSummary = [
                'total_earned' => (float) (clone $contractProjectsQuery)->sum('contract_employee_total_earned'),
                'payable' => (float) (clone $contractProjectsQuery)->sum('contract_employee_payable'),
            ];

            $contractProjects = (clone $contractProjectsQuery)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get([
                    'id',
                    'name',
                    'status',
                    'contract_employee_total_earned',
                    'contract_employee_payable',
                    'currency',
                ]);
        }

        return view('employee.dashboard', [
            'totalProjects' => $totalProjects,
            'projectStatusCounts' => $projectStatusCounts,
            'recentProjects' => $recentProjects,
            'taskStats' => $taskStats,
            'contractSummary' => $contractSummary,
            'contractProjects' => $contractProjects,
        ]);
    }
}
