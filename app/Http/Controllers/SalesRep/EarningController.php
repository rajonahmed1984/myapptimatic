<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\Project;
use Illuminate\Http\Request;

class EarningController extends Controller
{
    public function index(Request $request)
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

        return view('rep.earnings.index', [
            'earnings' => $earnings,
            'status' => $status,
            'statusOptions' => $statusOptions,
            'assignedProjects' => Project::query()
                ->with(['customer', 'salesRepresentatives' => fn ($q) => $q->whereKey($rep->id)])
                ->whereHas('salesRepresentatives', fn ($q) => $q->whereKey($rep->id))
                ->latest()
                ->get(),
        ]);
    }
}
