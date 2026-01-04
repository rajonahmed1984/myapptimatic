<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $rep = $request->attributes->get('salesRep');

        $payouts = CommissionPayout::query()
            ->where('sales_representative_id', $rep->id)
            ->latest()
            ->paginate(25);

        return view('rep.payouts.index', [
            'payouts' => $payouts,
        ]);
    }
}
