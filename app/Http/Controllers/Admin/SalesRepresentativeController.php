<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesRepresentativeController extends Controller
{
    public function index()
    {
        $reps = SalesRepresentative::query()
            ->with(['user:id,name,email', 'employee:id,name'])
            ->orderBy('name')
            ->get();

        $totals = CommissionEarning::query()
            ->selectRaw('sales_representative_id, SUM(commission_amount) as total_earned')
            ->selectRaw('SUM(CASE WHEN status = "payable" THEN commission_amount ELSE 0 END) as total_payable')
            ->selectRaw('SUM(CASE WHEN status = "paid" THEN commission_amount ELSE 0 END) as total_paid')
            ->groupBy('sales_representative_id')
            ->get()
            ->keyBy('sales_representative_id');

        return view('admin.sales-reps.index', [
            'reps' => $reps,
            'totals' => $totals,
        ]);
    }

    public function create()
    {
        $existingUserIds = SalesRepresentative::pluck('user_id')->filter()->all();

        $users = User::query()
            ->when($existingUserIds, fn ($q) => $q->whereNotIn('id', $existingUserIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $employees = Employee::orderBy('name')->get(['id', 'name']);

        return view('admin.sales-reps.create', [
            'users' => $users,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', Rule::unique('sales_representatives', 'user_id')],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user = User::findOrFail($data['user_id']);

        SalesRepresentative::create([
            'user_id' => $data['user_id'],
            'employee_id' => $data['employee_id'] ?? null,
            'name' => $data['name'] ?: $user->name,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('admin.sales-reps.index')
            ->with('status', 'Sales representative created.');
    }

    public function edit(SalesRepresentative $salesRep)
    {
        $employees = Employee::orderBy('name')->get(['id', 'name']);

        return view('admin.sales-reps.edit', [
            'rep' => $salesRep,
            'employees' => $employees,
        ]);
    }

    public function update(Request $request, SalesRepresentative $salesRep)
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $salesRep->update($data);

        return redirect()
            ->route('admin.sales-reps.index')
            ->with('status', 'Sales representative updated.');
    }

    public function show(SalesRepresentative $salesRep)
    {
        $salesRep->load(['user:id,name,email', 'employee:id,name']);

        $earningsQuery = $salesRep->earnings();
        $payoutsQuery = $salesRep->payouts();

        $summary = [
            'total_earned' => (float) $earningsQuery->sum('commission_amount'),
            'payable' => (float) $earningsQuery->where('status', 'payable')->sum('commission_amount'),
            'paid' => (float) $earningsQuery->where('status', 'paid')->sum('commission_amount'),
        ];

        $recentEarnings = $salesRep->earnings()
            ->latest()
            ->take(10)
            ->get();

        $recentPayouts = $salesRep->payouts()
            ->latest()
            ->take(10)
            ->get();

        return view('admin.sales-reps.show', [
            'rep' => $salesRep,
            'summary' => $summary,
            'recentEarnings' => $recentEarnings,
            'recentPayouts' => $recentPayouts,
        ]);
    }

    public function impersonate(Request $request, SalesRepresentative $salesRep)
    {
        if ($request->session()->has('impersonator_id')) {
            return back()->withErrors(['impersonate' => 'You are already impersonating another account. Stop impersonation first.']);
        }

        if ($salesRep->status !== 'active') {
            return back()->withErrors(['impersonate' => 'Sales rep access is inactive.']);
        }

        $user = $salesRep->user;
        if (! $user) {
            return back()->withErrors(['impersonate' => 'No linked user found for this sales rep.']);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('rep.dashboard');
    }
}
