<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\TaskSettings;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::query()
            ->with(['customer', 'maintenances'])
            ->where('customer_id', $request->user()->customer_id)
            ->latest()
            ->paginate(20);

        return view('client.projects.index', compact('projects'));
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $project->load('customer');

        $tasks = $project->tasks()
            ->where('customer_visible', true)
            ->orderBy('id')
            ->get();

        $maintenances = $project->maintenances()
            ->with(['invoices' => fn ($query) => $query->latest('issue_date')])
            ->orderBy('next_billing_date')
            ->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        return view('client.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'maintenances' => $maintenances,
            'initialInvoice' => $initialInvoice,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
        ]);
    }
}
