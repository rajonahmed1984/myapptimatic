<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeePayoutController extends Controller
{
    public function create(Request $request): View
    {
        $employeeId = $request->query('employee_id');
        $employees = Employee::query()
            ->whereHas('activeCompensation', fn ($query) => $query->where('salary_type', 'project_base'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $earnings = collect();
        $summary = [
            'total' => 0.0,
            'paid' => 0.0,
            'payable' => 0.0,
            'currency' => 'BDT',
        ];

        if ($employeeId) {
            $employee = Employee::query()->with('activeCompensation')->find($employeeId);
            if ($employee) {
                $contractProjects = Project::query()
                    ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
                    ->whereNotNull('contract_employee_payable')
                    ->where('contract_employee_payable', '>', 0)
                    ->orderByDesc('updated_at')
                    ->get([
                        'id',
                        'name',
                        'currency',
                        'contract_employee_total_earned',
                        'contract_employee_payable',
                        'contract_employee_payout_status',
                        'updated_at',
                    ]);

                $totalEarned = (float) $contractProjects->sum('contract_employee_total_earned');
                $payableRaw = (float) $contractProjects->sum('contract_employee_payable');
                $paid = (float) EmployeePayout::query()
                    ->where('employee_id', $employee->id)
                    ->sum('amount');

                $earnings = $contractProjects;
                $summary = [
                    'total' => $totalEarned,
                    'paid' => $paid,
                    'payable' => max(0, $payableRaw - $paid),
                    'currency' => $employee->activeCompensation?->currency
                        ?: ($contractProjects->first()?->currency ?? 'BDT'),
                ];
            }
        }

        return view('admin.hr.employees.payouts.create', [
            'employees' => $employees,
            'selectedEmployee' => $employeeId,
            'earnings' => $earnings,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['integer', 'distinct'],
            'payout_method' => ['nullable', 'in:bank,mobile,cash'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $employee = Employee::query()->with('activeCompensation')->findOrFail($data['employee_id']);

        $projects = Project::query()
            ->whereIn('id', $data['project_ids'])
            ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
            ->whereNotNull('contract_employee_payable')
            ->where('contract_employee_payable', '>', 0)
            ->get(['id', 'currency', 'contract_employee_payable']);

        if ($projects->isEmpty()) {
            return back()->withErrors(['project_ids' => 'Select at least one payable project.'])->withInput();
        }

        $currency = $employee->activeCompensation?->currency ?? ($projects->first()?->currency ?? 'BDT');
        $amount = (float) $projects->sum('contract_employee_payable');

        if ($amount <= 0) {
            return back()->withErrors(['project_ids' => 'Selected projects have no payable amount.'])->withInput();
        }

        $payableRaw = (float) Project::query()
            ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
            ->whereNotNull('contract_employee_payable')
            ->where('contract_employee_payable', '>', 0)
            ->sum('contract_employee_payable');
        $paidTotal = (float) EmployeePayout::query()
            ->where('employee_id', $employee->id)
            ->sum('amount');
        $outstanding = max(0, $payableRaw - $paidTotal);

        if ($amount > $outstanding) {
            return back()->withErrors(['project_ids' => 'Selected projects exceed the current payable balance.'])->withInput();
        }

        EmployeePayout::create([
            'employee_id' => $employee->id,
            'amount' => $amount,
            'currency' => $currency,
            'payout_method' => $data['payout_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'metadata' => ['project_ids' => $projects->pluck('id')->all()],
            'paid_at' => now(),
        ]);

        return redirect()
            ->route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'payouts'])
            ->with('status', 'Employee payout recorded.');
    }
}
