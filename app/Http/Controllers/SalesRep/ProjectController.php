<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SalesRepresentative;
use App\Support\TaskSettings;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $repId = SalesRepresentative::where('user_id', $request->user()->id)->value('id');

        $projects = Project::query()
            ->with([
                'customer',
                'salesRepresentatives' => fn ($q) => $q->whereKey($repId),
            ])
            ->whereHas('salesRepresentatives', fn ($q) => $q->whereKey($repId))
            ->latest()
            ->paginate(20);

        $commissionMap = $projects->getCollection()
            ->mapWithKeys(function (Project $project) {
                $rep = $project->salesRepresentatives->first();
                $amount = $rep?->pivot?->amount;
                return [$project->id => $amount !== null ? (float) $amount : null];
            })
            ->all();

        return view('rep.projects.index', [
            'projects' => $projects,
            'commissionMap' => $commissionMap,
        ]);
    }

    public function show(Request $request, Project $project)
    {
        $repId = SalesRepresentative::where('user_id', $request->user()->id)->value('id');
        $this->authorize('view', $project);

        $project->load([
            'customer',
            'salesRepresentatives' => fn ($q) => $q->whereKey($repId),
        ]);
        $repAmount = $project->salesRepresentatives->first()?->pivot?->amount;

        $tasks = $project->tasks()
            ->orderBy('id')
            ->get();

        $maintenances = $project->maintenances()
            ->where('sales_rep_visible', true)
            ->with(['invoices' => fn ($query) => $query->latest('issue_date')])
            ->orderBy('next_billing_date')
            ->get();

        $initialInvoice = $project->invoices()
            ->where('type', 'project_initial_payment')
            ->latest('issue_date')
            ->first();

        return view('rep.projects.show', [
            'project' => $project,
            'tasks' => $tasks,
            'maintenances' => $maintenances,
            'initialInvoice' => $initialInvoice,
            'taskTypeOptions' => TaskSettings::taskTypeOptions(),
            'priorityOptions' => TaskSettings::priorityOptions(),
            'salesRepAmount' => $repAmount !== null ? (float) $repAmount : null,
        ]);
    }
}
