<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SalesRepresentative;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $repId = SalesRepresentative::where('user_id', $request->user()->id)->value('id');

        $projects = Project::query()
            ->with('customer')
            ->whereHas('salesRepresentatives', fn ($q) => $q->whereKey($repId))
            ->latest()
            ->paginate(20);

        return view('rep.projects.index', compact('projects'));
    }

    public function show(Request $request, Project $project)
    {
        $repId = SalesRepresentative::where('user_id', $request->user()->id)->value('id');
        $this->authorize('view', $project);

        $project->load(['customer']);

        $tasks = $project->tasks()
            ->where(function ($q) use ($repId) {
                $q->where(function ($qq) use ($repId) {
                    $qq->where('assigned_type', 'sales_rep')->where('assigned_id', $repId);
                })->orWhere('customer_visible', true);
            })
            ->orderBy('id')
            ->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        return view('rep.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'initialInvoice' => $initialInvoice,
        ]);
    }
}
