<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Customer;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function index(Request $request)
    {
        $query = Affiliate::with('customer');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('affiliate_code', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $affiliates = $query->latest()->paginate(20);

        return view('admin.affiliates.index', compact('affiliates'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        
        return view('admin.affiliates.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id', 'unique:affiliates,customer_id'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'fixed_commission_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'payment_details' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['affiliate_code'] = Affiliate::generateUniqueCode();
        $data['approved_at'] = $data['status'] === 'active' ? now() : null;

        Affiliate::create($data);

        return redirect()->route('admin.affiliates.index')
            ->with('status', 'Affiliate created successfully.');
    }

    public function show(Affiliate $affiliate)
    {
        $affiliate->load(['customer', 'referrals', 'commissions.invoice', 'payouts']);

        $stats = [
            'total_clicks' => $affiliate->referrals()->count(),
            'total_conversions' => $affiliate->referrals()->where('status', 'converted')->count(),
            'conversion_rate' => $affiliate->referrals()->count() > 0 
                ? round(($affiliate->referrals()->where('status', 'converted')->count() / $affiliate->referrals()->count()) * 100, 2) 
                : 0,
            'pending_commissions' => $affiliate->commissions()->where('status', 'pending')->sum('amount'),
            'approved_commissions' => $affiliate->commissions()->where('status', 'approved')->sum('amount'),
        ];

        return view('admin.affiliates.show', compact('affiliate', 'stats'));
    }

    public function edit(Affiliate $affiliate)
    {
        $customers = Customer::orderBy('name')->get();

        return view('admin.affiliates.edit', compact('affiliate', 'customers'));
    }

    public function update(Request $request, Affiliate $affiliate)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id', 'unique:affiliates,customer_id,'.$affiliate->id],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'fixed_commission_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'payment_details' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['status'] === 'active' && ! $affiliate->approved_at) {
            $data['approved_at'] = now();
        }

        $affiliate->update($data);

        return redirect()->route('admin.affiliates.show', $affiliate)
            ->with('status', 'Affiliate updated successfully.');
    }

    public function destroy(Affiliate $affiliate)
    {
        $affiliate->delete();

        return redirect()->route('admin.affiliates.index')
            ->with('status', 'Affiliate deleted successfully.');
    }
}
