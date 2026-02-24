<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AffiliateController extends Controller
{
    public function index(Request $request): InertiaResponse
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

        $affiliates = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Affiliates/Index', [
            'pageTitle' => 'Affiliates',
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'status' => (string) $request->query('status', ''),
            ],
            'status_options' => [
                ['value' => '', 'label' => 'All statuses'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'suspended', 'label' => 'Suspended'],
            ],
            'routes' => [
                'index' => route('admin.affiliates.index'),
                'create' => route('admin.affiliates.create'),
            ],
            'affiliates' => $affiliates->getCollection()->map(function (Affiliate $affiliate) {
                return [
                    'id' => $affiliate->id,
                    'customer_name' => (string) ($affiliate->customer?->name ?? 'Unknown'),
                    'customer_email' => (string) ($affiliate->customer?->email ?? '--'),
                    'affiliate_code' => (string) $affiliate->affiliate_code,
                    'status' => (string) $affiliate->status,
                    'status_label' => ucfirst((string) $affiliate->status),
                    'commission_display' => $affiliate->commission_type === 'percentage'
                        ? ((string) $affiliate->commission_rate . '%')
                        : ('$' . number_format((float) $affiliate->fixed_commission_amount, 2)),
                    'balance_display' => '$' . number_format((float) $affiliate->balance, 2),
                    'referrals_display' => (string) $affiliate->total_referrals . ' / ' . (string) $affiliate->total_conversions,
                    'routes' => [
                        'show' => route('admin.affiliates.show', $affiliate),
                    ],
                ];
            })->values()->all(),
            'pagination' => [
                'has_pages' => $affiliates->hasPages(),
                'current_page' => $affiliates->currentPage(),
                'last_page' => $affiliates->lastPage(),
                'previous_url' => $affiliates->previousPageUrl(),
                'next_url' => $affiliates->nextPageUrl(),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        $customers = Customer::orderBy('name')->get();

        return Inertia::render('Admin/Affiliates/Form', $this->formInertiaProps(
            affiliate: null,
            customers: $customers
        ));
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

    public function show(Affiliate $affiliate): InertiaResponse
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

        return Inertia::render('Admin/Affiliates/Show', [
            'pageTitle' => 'Affiliate Details',
            'affiliate' => [
                'id' => $affiliate->id,
                'customer_name' => (string) ($affiliate->customer?->name ?? 'Unknown'),
                'customer_email' => (string) ($affiliate->customer?->email ?? '--'),
                'affiliate_code' => (string) $affiliate->affiliate_code,
                'status' => (string) $affiliate->status,
                'status_label' => ucfirst((string) $affiliate->status),
                'commission_type' => (string) $affiliate->commission_type,
                'commission_rate' => (float) $affiliate->commission_rate,
                'fixed_commission_amount' => (float) ($affiliate->fixed_commission_amount ?? 0),
                'commission_display' => $affiliate->commission_type === 'percentage'
                    ? ((string) $affiliate->commission_rate . '%')
                    : ('$' . number_format((float) $affiliate->fixed_commission_amount, 2)),
                'total_earned_display' => '$' . number_format((float) $affiliate->total_earned, 2),
                'balance_display' => '$' . number_format((float) $affiliate->balance, 2),
                'total_referrals' => (int) $affiliate->total_referrals,
                'total_conversions' => (int) $affiliate->total_conversions,
            ],
            'stats' => [
                'total_clicks' => (int) $stats['total_clicks'],
                'total_conversions' => (int) $stats['total_conversions'],
                'conversion_rate' => (float) $stats['conversion_rate'],
                'pending_commissions_display' => '$' . number_format((float) $stats['pending_commissions'], 2),
                'approved_commissions_display' => '$' . number_format((float) $stats['approved_commissions'], 2),
            ],
            'routes' => [
                'index' => route('admin.affiliates.index'),
                'edit' => route('admin.affiliates.edit', $affiliate),
                'destroy' => route('admin.affiliates.destroy', $affiliate),
            ],
        ]);
    }

    public function edit(Affiliate $affiliate): InertiaResponse
    {
        $customers = Customer::orderBy('name')->get();

        $affiliate->loadMissing('customer');

        return Inertia::render('Admin/Affiliates/Form', $this->formInertiaProps(
            affiliate: $affiliate,
            customers: $customers
        ));
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

    private function formInertiaProps(?Affiliate $affiliate, $customers): array
    {
        $isEdit = $affiliate !== null;

        return [
            'pageTitle' => $isEdit ? 'Edit Affiliate' : 'Create Affiliate',
            'is_edit' => $isEdit,
            'affiliate' => $affiliate ? [
                'id' => $affiliate->id,
                'customer_name' => (string) ($affiliate->customer?->name ?? 'Unknown'),
            ] : null,
            'form' => [
                'action' => $isEdit
                    ? route('admin.affiliates.update', $affiliate)
                    : route('admin.affiliates.store'),
                'method' => $isEdit ? 'PUT' : 'POST',
                'fields' => [
                    'customer_id' => (string) old('customer_id', (string) ($affiliate?->customer_id ?? '')),
                    'status' => (string) old('status', (string) ($affiliate?->status ?? 'active')),
                    'commission_type' => (string) old('commission_type', (string) ($affiliate?->commission_type ?? 'percentage')),
                    'commission_rate' => (string) old('commission_rate', (string) ($affiliate?->commission_rate ?? '10.00')),
                    'fixed_commission_amount' => (string) old('fixed_commission_amount', (string) ($affiliate?->fixed_commission_amount ?? '')),
                    'payment_details' => (string) old('payment_details', (string) ($affiliate?->payment_details ?? '')),
                    'notes' => (string) old('notes', (string) ($affiliate?->notes ?? '')),
                ],
            ],
            'status_options' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'suspended', 'label' => 'Suspended'],
            ],
            'commission_type_options' => [
                ['value' => 'percentage', 'label' => 'Percentage'],
                ['value' => 'fixed', 'label' => 'Fixed Amount'],
            ],
            'customers' => $customers->map(function (Customer $customer) {
                return [
                    'id' => $customer->id,
                    'name' => (string) $customer->name,
                    'email' => (string) ($customer->email ?? ''),
                ];
            })->values()->all(),
            'routes' => [
                'index' => route('admin.affiliates.index'),
                'show' => $affiliate ? route('admin.affiliates.show', $affiliate) : null,
            ],
        ];
    }
}
