<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
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
            ->with('customer')
            ->whereHas('salesRepresentatives', fn ($q) => $q->whereKey($repId))
            ->latest()
            ->paginate(20);

        $commissionMap = [];
        $projectIds = $projects->getCollection()->pluck('id')->all();
        if (! empty($projectIds)) {
            $commissions = CommissionEarning::query()
                ->where('sales_representative_id', $repId)
                ->whereIn('project_id', $projectIds)
                ->selectRaw('project_id, currency, SUM(commission_amount) as total')
                ->groupBy('project_id', 'currency')
                ->get();

            $commissionMap = $commissions
                ->groupBy('project_id')
                ->map(function ($rows) {
                    return $rows->map(function ($row) {
                        return [
                            'amount' => (float) $row->total,
                            'currency' => (string) $row->currency,
                        ];
                    })->values();
                })
                ->all();
        }

        return view('rep.projects.index', [
            'projects' => $projects,
            'commissionMap' => $commissionMap,
        ]);
    }

    public function show(Request $request, Project $project)
    {
        $repId = SalesRepresentative::where('user_id', $request->user()->id)->value('id');
        $this->authorize('view', $project);

        $project->load(['customer']);

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
        ]);
    }
}
