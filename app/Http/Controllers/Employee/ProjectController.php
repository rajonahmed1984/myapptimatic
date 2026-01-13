<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Models\Employee;
use App\Support\TaskSettings;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user(); // guard: employee
        $employeeId = $employee?->id;

        $projects = Project::query()
            ->with(['customer'])
            ->whereHas('employees', fn ($q) => $q->whereKey($employeeId))
            ->latest()
            ->paginate(20);

        return view('employee.projects.index', compact('projects'));
    }

    public function show(Request $request, Project $project)
    {
        $employee = $request->user(); // guard: employee
        $this->authorize('view', $project);

        $project->load(['customer']);

        $tasks = $project->tasks()
            ->orderBy('id')
            ->get();

        $employees = Employee::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $salesReps = []; // employees do not assign sales reps; left empty

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        return view('employee.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'employees' => $employees,
            'salesReps' => $salesReps,
            'initialInvoice' => $initialInvoice,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
        ]);
    }
}
