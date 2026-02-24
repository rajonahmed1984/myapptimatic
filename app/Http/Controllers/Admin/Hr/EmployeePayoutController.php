<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\PaymentMethod;
use App\Models\Project;
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

        if ($request->hasFile('payment_proof')) {
            $file = $request->file('payment_proof');
            $metadata['payment_proof_path'] = $file->store('employee-payout-proofs', 'public');
            $metadata['payment_proof_name'] = $file->getClientOriginalName();
            $metadata['payment_proof_mime'] = $file->getClientMimeType();
        }

        EmployeePayout::create([
            'employee_id' => $employee->id,
            'amount' => (float) $data['amount'],
            'currency' => $currency,
            'payout_method' => $data['payout_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'metadata' => $metadata,
            'paid_at' => ! empty($data['paid_at']) ? Carbon::parse((string) $data['paid_at'])->startOfDay() : now(),
        ]);

        return back()->with('status', 'Advance payout recorded.');
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
}
