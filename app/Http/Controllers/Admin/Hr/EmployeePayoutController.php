<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmployeePaymentReceiptJob;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\PaymentMethod;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EmployeePayoutController extends Controller
{
    public function create(Request $request): InertiaResponse
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

        return Inertia::render('Admin/Hr/Employees/Payouts/Create', [
            'pageTitle' => 'Employee Payout',
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
            ])->values(),
            'selectedEmployee' => $employeeId ? (int) $employeeId : null,
            'earnings' => $earnings,
            'summary' => $summary,
            'paymentMethods' => PaymentMethod::dropdownOptions()
                ->map(fn ($method) => [
                    'code' => $method->code,
                    'name' => $method->name,
                ])
                ->values(),
            'routes' => [
                'employeesIndex' => route('admin.hr.employees.index'),
                'create' => route('admin.hr.employee-payouts.create'),
                'store' => route('admin.hr.employee-payouts.store'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['integer', 'distinct'],
            'payout_method' => ['nullable', Rule::in(PaymentMethod::allowedCodes())],
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
        $selectedAmount = (float) $projects->sum('contract_employee_payable');

        if ($selectedAmount <= 0) {
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
        $outstanding = round(max(0, $payableRaw - $paidTotal), 2, PHP_ROUND_HALF_UP);
        $amount = round(min($selectedAmount, $outstanding), 2, PHP_ROUND_HALF_UP);

        if ($amount <= 0) {
            return back()->withErrors(['project_ids' => 'No payable balance is available for payout.'])->withInput();
        }

        $payout = EmployeePayout::create([
            'employee_id' => $employee->id,
            'amount' => $amount,
            'currency' => $currency,
            'payout_method' => $data['payout_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'metadata' => [
                'project_ids' => $projects->pluck('id')->all(),
                'selected_amount' => $selectedAmount,
                'applied_amount' => $amount,
                'outstanding_before' => $outstanding,
            ],
            'paid_at' => now(),
        ]);

        SendEmployeePaymentReceiptJob::dispatch('employee_payout', $payout->id)->afterCommit();

        return redirect()
            ->route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => 'payouts'])
            ->with('status', 'Employee payout recorded.');
    }

    public function storeAdvance(Request $request, Employee $employee): RedirectResponse
    {
        $employee->loadMissing('activeCompensation');
        $salaryType = $employee->activeCompensation?->salary_type;

        if (! $salaryType) {
            return back()->withErrors(['amount' => 'Employee compensation setup is required before recording an advance.']);
        }

        $data = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'coordination_month' => [
                Rule::requiredIf(fn () => $salaryType !== 'project_base'),
                'nullable',
                'regex:/^\d{4}-\d{2}$/',
            ],
            'payout_method' => ['nullable', Rule::in(PaymentMethod::allowedCodes())],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date_format:Y-m-d'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $currency = $data['currency'] ?? ($employee->activeCompensation?->currency ?? 'BDT');
        $project = null;

        if ($salaryType === 'project_base' && ! empty($data['project_id'])) {
            $project = Project::query()
                ->with('customer:id,name')
                ->whereKey((int) $data['project_id'])
                ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
                ->first();

            if (! $project) {
                return back()->withErrors(['project_id' => 'Select a valid project linked to this employee.'])->withInput();
            }
        }

        $metadata = [
            'type' => 'advance',
            'salary_type' => $salaryType,
            'advance_scope' => $salaryType === 'project_base' ? 'project_payout' : 'payroll',
            'project_id' => $project?->id,
            'project_name' => $project?->name,
        ];

        $coordinationMonth = (string) ($data['coordination_month'] ?? '');
        if ($coordinationMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $coordinationMonth)) {
            try {
                $coordinationDate = Carbon::createFromFormat('Y-m', $coordinationMonth)->startOfMonth();
                $metadata['coordination_month'] = $coordinationMonth;
                $metadata['coordination_month_label'] = $coordinationDate->format('F Y');
            } catch (\Throwable) {
            }
        }

        if ($request->hasFile('payment_proof')) {
            $file = $request->file('payment_proof');
            $metadata['payment_proof_path'] = $file->store('employee-payout-proofs', 'public');
            $metadata['payment_proof_name'] = $file->getClientOriginalName();
            $metadata['payment_proof_mime'] = $file->getClientMimeType();
        }

        $payout = EmployeePayout::create([
            'employee_id' => $employee->id,
            'amount' => (float) $data['amount'],
            'currency' => $currency,
            'payout_method' => $data['payout_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'metadata' => $metadata,
            'paid_at' => ! empty($data['paid_at']) ? Carbon::parse((string) $data['paid_at'])->startOfDay() : now(),
        ]);

        if (($metadata['advance_scope'] ?? null) === 'payroll') {
            $this->syncEmployeePayrollAdvanceForPeriod(
                $employee,
                (string) ($metadata['coordination_month'] ?? '')
            );
        }

        SendEmployeePaymentReceiptJob::dispatch('employee_payout', $payout->id)->afterCommit();

        return back()->with('status', 'Advance payout recorded.');
    }

    public function updateAdvance(Request $request, Employee $employee, EmployeePayout $employeePayout): RedirectResponse
    {
        if ((int) $employeePayout->employee_id !== (int) $employee->id) {
            abort(404);
        }

        $metadata = is_array($employeePayout->metadata) ? $employeePayout->metadata : [];
        $previousCoordinationMonth = (string) ($metadata['coordination_month'] ?? '');
        $isPayrollAdvance = ($metadata['type'] ?? null) === 'advance'
            && ($metadata['advance_scope'] ?? null) === 'payroll';

        if (! $isPayrollAdvance) {
            return back()->withErrors(['amount' => 'Only payroll salary advances can be edited here.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'coordination_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'payout_method' => ['nullable', Rule::in(PaymentMethod::allowedCodes())],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date_format:Y-m-d'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $currency = $data['currency'] ?? ($employeePayout->currency ?: ($employee->activeCompensation?->currency ?? 'BDT'));
        $metadata['type'] = 'advance';
        $metadata['advance_scope'] = 'payroll';
        $metadata['salary_type'] = $metadata['salary_type'] ?? ($employee->activeCompensation?->salary_type ?? 'monthly');
        $metadata['project_id'] = null;
        $metadata['project_name'] = null;

        $coordinationMonth = (string) $data['coordination_month'];
        try {
            $coordinationDate = Carbon::createFromFormat('Y-m', $coordinationMonth)->startOfMonth();
            $metadata['coordination_month'] = $coordinationMonth;
            $metadata['coordination_month_label'] = $coordinationDate->format('F Y');
        } catch (\Throwable) {
            return back()->withErrors(['coordination_month' => 'Invalid coordination month.'])->withInput();
        }

        if ($request->hasFile('payment_proof')) {
            $oldPath = (string) ($metadata['payment_proof_path'] ?? '');
            if ($oldPath !== '' && ! str_contains($oldPath, '..') && str_starts_with($oldPath, 'employee-payout-proofs/')) {
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('payment_proof');
            $metadata['payment_proof_path'] = $file->store('employee-payout-proofs', 'public');
            $metadata['payment_proof_name'] = $file->getClientOriginalName();
            $metadata['payment_proof_mime'] = $file->getClientMimeType();
        }

        $employeePayout->update([
            'amount' => (float) $data['amount'],
            'currency' => $currency,
            'payout_method' => $data['payout_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'metadata' => $metadata,
            'paid_at' => ! empty($data['paid_at']) ? Carbon::parse((string) $data['paid_at'])->startOfDay() : now(),
        ]);

        $this->syncEmployeePayrollAdvanceForPeriod($employee, $coordinationMonth);
        if ($previousCoordinationMonth !== '' && $previousCoordinationMonth !== $coordinationMonth) {
            $this->syncEmployeePayrollAdvanceForPeriod($employee, $previousCoordinationMonth);
        }

        return back()->with('status', 'Salary advance updated.');
    }

    public function destroyAdvance(Employee $employee, EmployeePayout $employeePayout): RedirectResponse
    {
        if ((int) $employeePayout->employee_id !== (int) $employee->id) {
            abort(404);
        }

        $metadata = is_array($employeePayout->metadata) ? $employeePayout->metadata : [];
        $coordinationMonth = (string) ($metadata['coordination_month'] ?? '');
        $isPayrollAdvance = ($metadata['type'] ?? null) === 'advance'
            && ($metadata['advance_scope'] ?? null) === 'payroll';

        if (! $isPayrollAdvance) {
            return back()->withErrors(['amount' => 'Only payroll salary advances can be deleted here.']);
        }

        $path = (string) ($metadata['payment_proof_path'] ?? '');
        if ($path !== '' && ! str_contains($path, '..') && str_starts_with($path, 'employee-payout-proofs/')) {
            Storage::disk('public')->delete($path);
        }

        $employeePayout->delete();

        $this->syncEmployeePayrollAdvanceForPeriod($employee, $coordinationMonth);

        return back()->with('status', 'Salary advance deleted.');
    }

    public function proof(EmployeePayout $employeePayout)
    {
        $path = (string) ($employeePayout->metadata['payment_proof_path'] ?? '');

        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'employee-payout-proofs/')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $filename = (string) ($employeePayout->metadata['payment_proof_name'] ?? basename($path));

        return Storage::disk('public')->response($path, $filename);
    }

    private function syncEmployeePayrollAdvanceForPeriod(Employee $employee, string $periodKey): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $periodKey)) {
            return;
        }

        $period = PayrollPeriod::query()
            ->where('period_key', $periodKey)
            ->first(['id', 'period_key', 'status', 'start_date', 'end_date']);

        if (! $period || ! $period->start_date || ! $period->end_date || $period->status !== 'draft') {
            return;
        }

        $payrollService = app(PayrollService::class);
        $expectedByEmployee = $payrollService->coordinatedPayrollAdvancesByEmployee(
            (string) $period->period_key,
            $period->start_date->copy()->startOfDay(),
            $period->end_date->copy()->endOfDay()
        );
        $expectedAdvance = round((float) ($expectedByEmployee[$employee->id] ?? 0), 2, PHP_ROUND_HALF_UP);

        $items = PayrollItem::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->where('status', '!=', 'paid')
            ->get(['id', 'advances', 'net_pay']);

        foreach ($items as $item) {
            $currentAdvance = round($this->sumPayrollAdjustment($item->advances), 2, PHP_ROUND_HALF_UP);
            if (abs($currentAdvance - $expectedAdvance) < 0.005) {
                continue;
            }

            $newNet = round((float) ($item->net_pay ?? 0) + $currentAdvance - $expectedAdvance, 2, PHP_ROUND_HALF_UP);
            $item->update([
                'advances' => $expectedAdvance,
                'net_pay' => $newNet,
            ]);
        }
    }

    private function sumPayrollAdjustment($value): float
    {
        if (is_array($value)) {
            return (float) array_reduce($value, function ($carry, $row) {
                return $carry + (float) ($row['amount'] ?? $row ?? 0);
            }, 0.0);
        }

        return (float) ($value ?? 0);
    }
}
