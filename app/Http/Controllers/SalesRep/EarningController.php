<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EarningController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $rep = $request->attributes->get('salesRep');
        $status = $request->query('status');

        $query = CommissionEarning::query()
            ->where('sales_representative_id', $rep->id)
            ->with(['invoice', 'subscription', 'project', 'customer'])
            ->latest('earned_at');

        if ($status) {
            $query->where('status', $status);
        }

        $earnings = $query->paginate(25)->withQueryString();

        $statusOptions = ['pending', 'earned', 'payable', 'paid', 'reversed'];

        $assignedProjects = Project::query()
            ->with(['customer', 'salesRepresentatives' => fn ($q) => $q->whereKey($rep->id)])
            ->whereHas('salesRepresentatives', fn ($q) => $q->whereKey($rep->id))
            ->latest()
            ->get();

        return Inertia::render('Rep/Earnings/Index', [
            'earnings' => $earnings->getCollection()->map(function (CommissionEarning $earning) {
                return [
                    'id' => $earning->id,
                    'source_type' => ucfirst((string) $earning->source_type),
                    'source_label' => $earning->invoice
                        ? 'Invoice #'.$earning->invoice->id
                        : ($earning->project ? 'Project #'.$earning->project->id : null),
                    'customer_name' => $earning->customer?->name ?? '--',
                    'paid_amount' => (float) ($earning->paid_amount ?? 0),
                    'commission_amount' => (float) ($earning->commission_amount ?? 0),
                    'currency' => $earning->currency,
                    'status_label' => ucfirst((string) $earning->status),
                    'earned_at_display' => $earning->earned_at?->format(config('app.date_format', 'Y-m-d').' H:i') ?? '--',
                ];
            })->values()->all(),
            'status' => $status,
            'status_options' => $statusOptions,
            'assigned_projects' => $assignedProjects->map(function (Project $project) {
                $rep = $project->salesRepresentatives->first();
                $amount = $rep?->pivot?->amount;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'customer_name' => $project->customer?->name ?? '--',
                    'commission_amount' => $amount !== null ? (float) $amount : null,
                    'currency' => $project->currency,
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
                'per_page' => $earnings->perPage(),
                'total' => $earnings->total(),
                'from' => $earnings->firstItem(),
                'to' => $earnings->lastItem(),
                'prev_page_url' => $earnings->previousPageUrl(),
                'next_page_url' => $earnings->nextPageUrl(),
            ],
            'routes' => [
                'dashboard' => route('rep.dashboard'),
                'index' => route('rep.earnings.index'),
            ],
        ]);
    }
}
