<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View
    {
        $employees = Employee::query()
            ->with('manager')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.hr.employees.index', compact('employees'));
    }

    public function create(): View
    {
        $managers = Employee::query()->orderBy('name')->get();
        $users = User::query()->orderBy('name')->get();

        return view('admin.hr.employees.create', compact('managers', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', 'in:full_time,part_time,contract'],
            'work_mode' => ['required', 'in:remote,on_site,hybrid'],
            'join_date' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'salary_type' => ['required', 'in:monthly,hourly'],
            'currency' => ['required', 'string', 'max:10'],
            'basic_pay' => ['required', 'numeric'],
            'hourly_rate' => ['nullable', 'numeric'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $employeeData = collect($data)->only([
            'user_id',
            'manager_id',
            'name',
            'email',
            'phone',
            'address',
            'designation',
            'department',
            'employment_type',
            'work_mode',
            'join_date',
            'status',
        ])->toArray();

        $employee = Employee::create($employeeData);

        $uploadPaths = $this->handleUploads($request);
        if (! empty($uploadPaths)) {
            $employee->update($uploadPaths);
        }

        EmployeeCompensation::create([
            'employee_id' => $employee->id,
            'salary_type' => $data['salary_type'],
            'currency' => $data['currency'],
            'basic_pay' => $data['basic_pay'],
            'overtime_rate' => $data['hourly_rate'] ?? null,
            'effective_from' => $data['join_date'],
            'is_active' => true,
            'set_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee created.');
    }

    public function edit(Employee $employee): View
    {
        $managers = Employee::query()->where('id', '!=', $employee->id)->orderBy('name')->get();
        $users = User::query()->orderBy('name')->get();

        return view('admin.hr.employees.edit', compact('employee', 'managers', 'users'));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:employees,email,'.$employee->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', 'in:full_time,part_time,contract'],
            'work_mode' => ['required', 'in:remote,on_site,hybrid'],
            'join_date' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $employee->update($data);

        $uploadPaths = $this->handleUploads($request);
        if (! empty($uploadPaths)) {
            $employee->update($uploadPaths);
        }

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('admin.hr.employees.index')
            ->with('status', 'Employee removed.');
    }

    public function show(Employee $employee): View
    {
        $employee->load(['manager:id,name', 'user:id,name,email', 'activeCompensation']);

        $comp = $employee->activeCompensation;
        $summary = [
            'salary_type' => $comp?->salary_type,
            'basic_pay' => $comp?->basic_pay,
            'currency' => $comp?->currency,
        ];

        $tab = request()->query('tab', 'summary');
        $allowedTabs = ['summary', 'profile', 'compensation', 'timesheets', 'leave', 'payroll'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'summary';
        }

        return view('admin.hr.employees.show', [
            'employee' => $employee,
            'tab' => $tab,
            'summary' => $summary,
        ]);
    }

    public function impersonate(Request $request, Employee $employee): RedirectResponse
    {
        if ($request->session()->has('impersonator_id')) {
            return back()->withErrors(['impersonate' => 'You are already impersonating another account. Stop impersonation first.']);
        }

        if ($employee->status !== 'active') {
            return back()->withErrors(['impersonate' => 'Employee is not active.']);
        }

        $user = $employee->user;
        if (! $user) {
            return back()->withErrors(['impersonate' => 'No linked user found for this employee.']);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);
        Auth::guard('employee')->login($user);
        $request->session()->regenerate();

        return redirect()->route('employee.dashboard');
    }

    private function handleUploads(Request $request): array
    {
        $paths = [];

        if ($request->hasFile('nid_file')) {
            $paths['nid_path'] = $request->file('nid_file')->store('employees/nid', 'public');
        }

        if ($request->hasFile('photo')) {
            $paths['photo_path'] = $request->file('photo')->store('employees/photos', 'public');
        }

        if ($request->hasFile('cv_file')) {
            $paths['cv_path'] = $request->file('cv_file')->store('employees/cv', 'public');
        }

        return $paths;
    }
}
