@extends('layouts.admin')

@section('title', $employee->name)
@section('page-title', $employee->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Employee</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $employee->name }}</div>
            <div class="text-sm text-slate-500">{{ $employee->email }}</div>
        </div>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('admin.hr.employees.impersonate', $employee) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">
                    Login as Employee
                </button>
            </form>
            <a href="{{ route('admin.hr.employees.edit', $employee) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <a href="{{ route('admin.hr.employees.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-3 text-sm font-semibold text-slate-700">
        @php
            $salaryType = $summary['salary_type'] ?? null;
            $isProjectBase = ($salaryType === 'project_base');
            $isMonthly = ($salaryType === 'monthly');
            $tabs = [
                'profile' => 'Profile',
            ];
            if ($isProjectBase) {
                $tabs['earnings'] = 'Recent Earnings';
                $tabs['payouts'] = 'Recent Payouts';
            }
            $tabs['projects'] = 'Projects';
            if ($isMonthly) {
                $tabs['timesheets'] = 'Work Logs';
                $tabs['leave'] = 'Leave';
                $tabs['payroll'] = 'Payroll';
            }
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => $key]) }}"
               class="rounded-full border px-3 py-1 {{ $tab === $key ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($tab === 'profile')
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Status</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucfirst($employee->status) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Salary Type</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucwords(str_replace('_', ' ', $summary['salary_type'] ?? '--')) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Basic Pay</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $summary['currency'] ?? '' }} {{ number_format($summary['basic_pay'] ?? 0, 2) }}</div>
            </div>
        </div>

        @if($projectBaseEarnings)
            <div class="mt-4 grid gap-4 md:grid-cols-4">
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Total Earned</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($projectBaseEarnings['total_earned'] ?? 0, 2) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Payable</div>
                    <div class="mt-2 text-2xl font-semibold text-amber-700">{{ number_format($projectBaseEarnings['payable'] ?? 0, 2) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Paid</div>
                    <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($projectBaseEarnings['paid'] ?? 0, 2) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Advance Paid</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($projectBaseEarnings['advance_paid'] ?? 0, 2) }}</div>
                </div>
            </div>
        @endif

        @if(($summary['salary_type'] ?? null) === 'project_base')
            <div class="mt-4 card p-4">
                <div class="text-sm font-semibold text-slate-800">Record advance payout</div>
                <div class="text-xs text-slate-500">Advance payments are deducted from future project payouts.</div>
                <form method="POST" action="{{ route('admin.hr.employees.advance-payout', $employee) }}" enctype="multipart/form-data" class="mt-3 grid gap-3 md:grid-cols-8">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Project filter</label>
                        <select id="employeeAdvanceProjectFilter" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="all">All projects</option>
                            <option value="active">Active projects</option>
                            <option value="complete">Completed projects</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Project</label>
                        <select id="employeeAdvanceProjectSelect" name="project_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Select project</option>
                            @foreach($advanceProjects ?? [] as $projectOption)
                                <option value="{{ $projectOption->id }}" data-status="{{ $projectOption->status ?? '' }}" @selected(old('project_id') == $projectOption->id)>
                                    {{ $projectOption->name }} @if($projectOption->customer) ({{ $projectOption->customer->name }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Amount</label>
                        <input name="amount" type="number" step="0.01" min="0" required value="{{ old('amount') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="0.00">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Currency</label>
                        <input name="currency" value="{{ old('currency', $summary['currency'] ?? 'BDT') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="BDT">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Method</label>
                        @php
                            $paymentMethods = \App\Models\PaymentMethod::dropdownOptions();
                        @endphp
                        <select name="payout_method" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Select</option>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->code }}" @selected(old('payout_method') === $method->code)>{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Reference</label>
                        <input name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Txn / Note">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Payment Date</label>
                        <input name="paid_at" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" value="{{ old('paid_at', now()->format(config('app.date_format', 'd-m-Y'))) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Payment Proof</label>
                        <input name="payment_proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-8">
                        <label class="text-xs text-slate-500">Note</label>
                        <input name="note" value="{{ old('note') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Optional note">
                    </div>
                    <div class="md:col-span-8 flex items-center gap-3">
                        <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save advance payout</button>
                    </div>
                </form>
            </div>
            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const filter = document.getElementById('employeeAdvanceProjectFilter');
                        const select = document.getElementById('employeeAdvanceProjectSelect');

                        if (!filter || !select) return;

                        const applyFilter = () => {
                            const value = filter.value;
                            const activeStatuses = ['ongoing'];
                            const completeStatuses = ['complete'];

                            [...select.options].forEach((option) => {
                                const status = option.dataset.status || '';
                                if (!status) {
                                    option.hidden = false;
                                    option.disabled = false;
                                    return;
                                }

                                const show = value === 'all'
                                    || (value === 'active' && activeStatuses.includes(status))
                                    || (value === 'complete' && completeStatuses.includes(status));

                                option.hidden = !show;
                                option.disabled = !show;
                            });
                        };

                        filter.addEventListener('change', applyFilter);
                        applyFilter();
                    });
                </script>
            @endpush
        @endif

        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Project Tasks</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $taskSummary['total'] ?? 0 }}</div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                    <span class="rounded-full border border-slate-300 bg-white px-2 py-1">Projects: {{ $taskSummary['projects'] ?? 0 }}</span>
                    <span class="rounded-full border border-slate-300 bg-white px-2 py-1">Pending: {{ $taskSummary['pending'] ?? 0 }}</span>
                    <span class="rounded-full border border-slate-300 bg-white px-2 py-1">In progress: {{ $taskSummary['in_progress'] ?? 0 }}</span>
                    <span class="rounded-full border border-slate-300 bg-white px-2 py-1">Blocked: {{ $taskSummary['blocked'] ?? 0 }}</span>
                    <span class="rounded-full border border-slate-300 bg-white px-2 py-1">Completed: {{ $taskSummary['completed'] ?? 0 }}</span>
                    @if(($taskSummary['other'] ?? 0) > 0)
                        <span class="rounded-full border border-slate-300 bg-white px-2 py-1">Other: {{ $taskSummary['other'] }}</span>
                    @endif
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Subtasks</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $subtaskSummary['total'] ?? 0 }}</div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                    <span class="rounded-full border border-slate-300 bg-white px-2 py-1">Completed: {{ $subtaskSummary['completed'] ?? 0 }}</span>
                    <span class="rounded-full border border-slate-300 bg-white px-2 py-1">Pending: {{ $subtaskSummary['pending'] ?? 0 }}</span>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Task Progress</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $taskProgress['percent'] ?? 0 }}%</div>
                <div class="mt-3">
                    <div class="h-2 w-full rounded-full bg-slate-200">
                        <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $taskProgress['percent'] ?? 0 }}%"></div>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">Based on completed tasks</div>
                </div>
            </div>
        </div>

        <div class="card p-6 mt-5">
            <div class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                <div><span class="font-semibold text-slate-900">Salary Type:</span> {{ ucwords(str_replace('_', ' ', $summary['salary_type'] ?? '--')) }}</div>
                <div><span class="font-semibold text-slate-900">Basic Pay:</span> {{ $summary['currency'] ?? '' }} {{ number_format($summary['basic_pay'] ?? 0, 2) }}</div>
                <div><span class="font-semibold text-slate-900">Effective From:</span> <span class="whitespace-nowrap tabular-nums">{{ $employee->activeCompensation?->effective_from?->format(config('app.date_format', 'd-m-Y')) ?? '--' }}</span></div>
                <div><span class="font-semibold text-slate-900">Department:</span> {{ $employee->department ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Designation:</span> {{ $employee->designation ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Manager:</span> {{ $employee->manager?->name ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Employment Type:</span> {{ ucfirst($employee->employment_type) }}</div>
                <div><span class="font-semibold text-slate-900">Work Mode:</span> {{ ucfirst(str_replace('_',' ',$employee->work_mode)) }}</div>
                <div><span class="font-semibold text-slate-900">Join Date:</span> <span class="whitespace-nowrap tabular-nums">{{ $employee->join_date?->format(config('app.date_format', 'd-m-Y')) ?? '--' }}</span></div>
                <div class="md:col-span-2"><span class="font-semibold text-slate-900">Address:</span> {{ $employee->address ?? '--' }}</div>
                <div class="md:col-span-2"><span class="font-semibold text-slate-900">Linked User:</span> {{ $employee->user?->name ? $employee->user->name.' ('.$employee->user->email.')' : '--' }}</div>
            </div>
        </div>

        <div class="mt-4 card p-6">
            <div class="text-sm font-semibold text-slate-800 mb-3">Documents</div>
            @php
                $nidPath = $employee->nid_path ?: $employee->user?->nid_path;
                $nidOwnerType = $employee->nid_path ? 'employee' : ($employee->user?->nid_path ? 'user' : null);
                $nidOwnerId = $employee->nid_path ? $employee->id : $employee->user?->id;
                $nidUrl = ($nidOwnerType && $nidOwnerId)
                    ? route('admin.user-documents.show', ['type' => $nidOwnerType, 'id' => $nidOwnerId, 'doc' => 'nid'])
                    : null;
                $nidIsImage = $nidPath
                    ? \Illuminate\Support\Str::endsWith(strtolower($nidPath), ['.jpg', '.jpeg', '.png', '.webp'])
                    : false;

                $cvPath = $employee->cv_path ?: $employee->user?->cv_path;
                $cvOwnerType = $employee->cv_path ? 'employee' : ($employee->user?->cv_path ? 'user' : null);
                $cvOwnerId = $employee->cv_path ? $employee->id : $employee->user?->id;
                $cvUrl = ($cvOwnerType && $cvOwnerId)
                    ? route('admin.user-documents.show', ['type' => $cvOwnerType, 'id' => $cvOwnerId, 'doc' => 'cv'])
                    : null;
                $cvIsImage = $cvPath
                    ? \Illuminate\Support\Str::endsWith(strtolower($cvPath), ['.jpg', '.jpeg', '.png', '.webp'])
                    : false;
            @endphp
            <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Avatar</div>
                    <div class="mt-2">
                        @php
                            $avatarPath = $employee->photo_path ?: $employee->user?->avatar_path;
                        @endphp
                        <x-avatar :path="$avatarPath" :name="$employee->name" size="h-16 w-16" textSize="text-sm" />
                    </div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">NID</div>
                    <div class="mt-2 flex items-center gap-3">
                        @if($nidUrl)
                            @if($nidIsImage)
                                <img src="{{ $nidUrl }}" alt="NID" class="h-16 w-20 rounded-lg object-cover border border-slate-300">
                            @else
                                <div class="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-300 bg-slate-50 text-xs font-semibold text-slate-500">PDF</div>
                            @endif
                            <a href="{{ $nidUrl }}" class="text-sm text-teal-600 hover:text-teal-500">View/Download</a>
                        @else
                            <div class="text-xs text-slate-500">Not uploaded</div>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">CV</div>
                    <div class="mt-2 flex items-center gap-3">
                        @if($cvUrl)
                            @if($cvIsImage)
                                <img src="{{ $cvUrl }}" alt="CV" class="h-16 w-20 rounded-lg object-cover border border-slate-300">
                            @else
                                <div class="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-300 bg-slate-50 text-xs font-semibold text-slate-500">PDF</div>
                            @endif
                            <a href="{{ $cvUrl }}" class="text-sm text-teal-600 hover:text-teal-500">View/Download</a>
                        @else
                            <div class="text-xs text-slate-500">Not uploaded</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @elseif(in_array($tab, ['payroll', 'timesheets'], true))
        @php
            $formatDuration = function (int $seconds): string {
                $hours = (int) floor($seconds / 3600);
                $minutes = (int) floor(($seconds % 3600) / 60);

                return sprintf('%02d:%02d', $hours, $minutes);
            };
        @endphp
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Worked Today</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatDuration((int) ($workSessionStats['today_active_seconds'] ?? 0)) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Worked This Month</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $formatDuration((int) ($workSessionStats['month_active_seconds'] ?? 0)) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Work Coverage</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ (int) ($workSessionStats['coverage_percent'] ?? 0) }}%</div>
                <div class="mt-2 text-xs text-slate-500">
                    Required: {{ $formatDuration((int) ($workSessionStats['month_required_seconds'] ?? 0)) }}
                </div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Today Salary Projection</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ $workSessionStats['currency'] ?? 'BDT' }} {{ number_format((float) ($workSessionStats['today_salary_projection'] ?? 0), 2) }}
                </div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Month Salary Projection</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ $workSessionStats['currency'] ?? 'BDT' }} {{ number_format((float) ($workSessionStats['month_salary_projection'] ?? 0), 2) }}
                </div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Payroll Source</div>
                <div class="mt-2 text-sm text-slate-700">
                    {{ $payrollSourceNote ?? '--' }}
                </div>
            </div>
        </div>

        @if($tab === 'payroll')
            <div class="card p-6 mt-5">
                @if(in_array($summary['salary_type'] ?? null, ['monthly', 'hourly'], true))
                    <div class="text-sm font-semibold text-slate-800">Record salary advance</div>
                    <div class="mt-1 text-xs text-slate-500">This creates an advance payout entry for payroll tracking.</div>
                    <form method="POST" action="{{ route('admin.hr.employees.advance-payout', $employee) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-6">
                        @csrf
                        <div>
                            <label class="text-xs text-slate-500">Amount</label>
                            <input name="amount" type="number" step="0.01" min="0" required value="{{ old('amount') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="0.00">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Currency</label>
                            <input name="currency" value="{{ old('currency', $summary['currency'] ?? 'BDT') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="BDT">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Method</label>
                            @php
                                $paymentMethods = \App\Models\PaymentMethod::dropdownOptions();
                            @endphp
                            <select name="payout_method" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="">Select</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->code }}" @selected(old('payout_method') === $method->code)>{{ $method->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Reference</label>
                            <input name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Txn / Note">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Payment Date</label>
                            <input name="paid_at" type="text" placeholder="DD-MM-YYYY" inputMode="numeric" value="{{ old('paid_at', now()->format(config('app.date_format', 'd-m-Y'))) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Payment Proof</label>
                            <input name="payment_proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Note</label>
                            <input name="note" value="{{ old('note') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Optional note">
                        </div>
                        <div class="md:col-span-6 flex items-center gap-3">
                            <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save salary advance</button>
                        </div>
                    </form>
                @else
                    <div class="text-sm text-slate-600">Payroll advance applies to monthly/hourly employees.</div>
                @endif
            </div>
        @endif
        
        @if($tab === 'timesheets')
            <div class="mt-4 card p-4">
                <div class="mb-3 text-sm font-semibold text-slate-800">Recent Work Sessions</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm text-slate-700">
                        <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th class="py-2 text-left">Date</th>
                                <th class="py-2 text-left">Started</th>
                                <th class="py-2 text-left">Ended</th>
                                <th class="py-2 text-left">Last Activity</th>
                                <th class="py-2 text-right">Active Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentWorkSessions as $session)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2 whitespace-nowrap tabular-nums">{{ $session->work_date?->format($globalDateFormat ?? 'd-m-Y') ?? '--' }}</td>
                                    <td class="py-2 whitespace-nowrap tabular-nums">{{ $session->started_at?->format($globalTimeFormat ?? 'h:i A') ?? '--' }}</td>
                                    <td class="py-2 whitespace-nowrap tabular-nums">{{ $session->ended_at?->format($globalTimeFormat ?? 'h:i A') ?? '--' }}</td>
                                    <td class="py-2 whitespace-nowrap tabular-nums">{{ $session->last_activity_at?->format($globalTimeFormat ?? 'h:i A') ?? '--' }}</td>
                                    <td class="py-2 text-right">{{ $formatDuration((int) ($session->active_seconds ?? 0)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-500">No work session data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 card p-4">
                <div class="mb-3 text-sm font-semibold text-slate-800">Daily Work Summaries</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm text-slate-700">
                        <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th class="py-2 text-left">Date</th>
                                <th class="py-2 text-right">Active</th>
                                <th class="py-2 text-right">Required</th>
                                <th class="py-2 text-right">Generated Salary</th>
                                <th class="py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentWorkSummaries as $summaryRow)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2 whitespace-nowrap tabular-nums">{{ $summaryRow->work_date?->format($globalDateFormat ?? 'd-m-Y') ?? '--' }}</td>
                                    <td class="py-2 text-right">{{ $formatDuration((int) ($summaryRow->active_seconds ?? 0)) }}</td>
                                    <td class="py-2 text-right">{{ $formatDuration((int) ($summaryRow->required_seconds ?? 0)) }}</td>
                                    <td class="py-2 text-right">{{ $summary['currency'] ?? 'BDT' }} {{ number_format((float) ($summaryRow->generated_salary_amount ?? 0), 2) }}</td>
                                    <td class="py-2">{{ ucfirst((string) ($summaryRow->status ?? 'generated')) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-500">No work summaries generated yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($tab === 'payroll')
            @if(in_array($summary['salary_type'] ?? null, ['monthly', 'hourly'], true))
                <div class="mt-4 card p-4">
                    <div class="mb-3 text-sm font-semibold text-slate-800">Salary Advance Transactions</div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-sm text-slate-700">
                            <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th class="py-2 text-left">Date</th>
                                    <th class="py-2 text-left">Amount</th>
                                    <th class="py-2 text-left">Method</th>
                                    <th class="py-2 text-left">Reference</th>
                                    <th class="py-2 text-left">Proof</th>
                                    <th class="py-2 text-left">Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentSalaryAdvances as $advance)
                                    @php
                                        $proofPath = $advance->metadata['payment_proof_path'] ?? null;
                                        $proofUrl = $proofPath ? route('admin.hr.employee-payouts.proof', $advance) : null;
                                    @endphp
                                    <tr class="border-b border-slate-100">
                                        <td class="py-2 whitespace-nowrap tabular-nums">{{ $advance->paid_at?->format(($globalDateTimeFormat ?? 'd-m-Y h:i A')) ?? '--' }}</td>
                                        <td class="py-2">{{ $advance->currency ?? 'BDT' }} {{ number_format((float) ($advance->amount ?? 0), 2) }}</td>
                                        <td class="py-2">{{ $advance->payout_method ? ucfirst(str_replace('_', ' ', (string) $advance->payout_method)) : '--' }}</td>
                                        <td class="py-2">{{ $advance->reference ?? '--' }}</td>
                                        <td class="py-2">
                                            @if($proofUrl)
                                                <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="text-teal-700 hover:text-teal-600">View/Download</a>
                                            @else
                                                <span class="text-slate-400">--</span>
                                            @endif
                                        </td>
                                        <td class="py-2">{{ $advance->note ?? '--' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-4 text-center text-slate-500">No salary advance transaction found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="mt-4 card p-4">
                <div class="mb-3 text-sm font-semibold text-slate-800">Recent Payroll Items</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1500px] text-sm text-slate-700">
                        <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th class="py-2 text-left">Period</th>
                                <th class="py-2 text-left">Pay Type</th>
                                <th class="py-2 text-right">Base</th>
                                <th class="py-2 text-right">Hours / Attendance</th>
                                <th class="py-2 text-right">Overtime</th>
                                <th class="py-2 text-right">Bonus</th>
                                <th class="py-2 text-right">Penalty</th>
                                <th class="py-2 text-right">Advance</th>
                                <th class="py-2 text-right">Est. Subtotal</th>
                                <th class="py-2 text-right">Gross</th>
                                <th class="py-2 text-right">Deduction</th>
                                <th class="py-2 text-right">Net</th>
                                <th class="py-2 text-left">Status</th>
                                <th class="py-2 text-left">Paid At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPayrollItems as $payrollItem)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2">{{ $payrollItem->period?->period_key ?? '--' }}</td>
                                    <td class="py-2">{{ ucfirst((string) ($payrollItem->pay_type ?? '--')) }}</td>
                                    <td class="py-2 text-right">{{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_base_pay ?? $payrollItem->base_pay ?? 0), 2) }}</td>
                                    <td class="py-2 text-right">{{ $payrollItem->computed_hours_attendance ?? '--' }}</td>
                                    <td class="py-2 text-right">
                                        {{ $payrollItem->computed_overtime_label ?? number_format((float) ($payrollItem->overtime_hours ?? 0), 2) }}
                                        <div class="text-[11px] text-slate-500">
                                            {{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_overtime_pay ?? 0), 2) }}
                                        </div>
                                    </td>
                                    <td class="py-2 text-right">{{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_bonus ?? 0), 2) }}</td>
                                    <td class="py-2 text-right">{{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_penalty ?? 0), 2) }}</td>
                                    <td class="py-2 text-right">{{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_advance ?? 0), 2) }}</td>
                                    <td class="py-2 text-right">{{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_est_subtotal ?? 0), 2) }}</td>
                                    <td class="py-2 text-right">{{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_gross_pay ?? $payrollItem->gross_pay ?? 0), 2) }}</td>
                                    <td class="py-2 text-right">{{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_deduction ?? 0), 2) }}</td>
                                    <td class="py-2 text-right">{{ $payrollItem->currency ?? '' }} {{ number_format((float) ($payrollItem->computed_net_pay ?? $payrollItem->net_pay ?? 0), 2) }}</td>
                                    <td class="py-2">{{ ucfirst((string) ($payrollItem->status ?? '--')) }}</td>
                                    <td class="py-2 whitespace-nowrap tabular-nums">{{ $payrollItem->paid_at?->format(($globalDateTimeFormat ?? 'd-m-Y h:i A')) ?? '--' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="py-4 text-center text-slate-500">No payroll item yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    @elseif($tab === 'earnings')
        @php
            $totalEarned = (float) ($projectBaseEarnings['total_earned'] ?? 0);
            $payable = (float) ($projectBaseEarnings['payable'] ?? 0);
            $paid = (float) ($projectBaseEarnings['paid'] ?? 0);
            $outstanding = max(0, $totalEarned - $paid);
        @endphp
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Earned Amount</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($totalEarned, 2) }}</div>
                <div class="text-xs text-slate-500">Includes earned, payable, and paid.</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Outstanding</div>
                <div class="mt-2 text-2xl font-semibold text-amber-700">{{ number_format($outstanding, 2) }}</div>
                <div class="text-xs text-slate-500">Amount yet to be paid.</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Payable</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($payable, 2) }}</div>
                <div class="text-xs text-slate-500">Ready for payout.</div>
            </div>
        </div>
        <div class="mt-4 card p-4">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Recent Earnings</div>
                @if($payable > 0)
                    <a href="{{ route('admin.hr.employee-payouts.create', ['employee_id' => $employee->id]) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">
                        Pay payable ({{ number_format($payable, 2) }})
                    </a>
                @else
                    <a href="{{ route('admin.hr.employee-payouts.create', ['employee_id' => $employee->id]) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">
                        Pay payable (0.00)
                    </a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm text-slate-700">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Status</th>
                            <th class="py-2 text-left">Source</th>
                            <th class="py-2 text-left">Details</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEarnings as $earning)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 whitespace-nowrap tabular-nums">{{ $earning->updated_at?->format($globalDateFormat ?? 'd-m-Y') ?? '--' }}</td>
                                <td class="py-2">{{ ucfirst($earning->contract_employee_payout_status ?? 'earned') }}</td>
                                <td class="py-2">Project</td>
                                <td class="py-2 text-xs text-slate-600">{{ $earning->name ?? '--' }}</td>
                                <td class="py-2 text-right">
                                    {{ $earning->currency ?? '' }} {{ number_format($earning->contract_employee_total_earned ?? 0, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-slate-500">No earnings yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($tab === 'payouts')
        <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Recent Payouts</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[500px] text-sm text-slate-700">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Reference</th>
                            <th class="py-2 text-left">Proof</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayouts as $payout)
                            @php
                                $proofPath = $payout->metadata['payment_proof_path'] ?? null;
                                $proofUrl = $proofPath ? route('admin.hr.employee-payouts.proof', $payout) : null;
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="py-2 whitespace-nowrap tabular-nums">{{ $payout->paid_at?->format($globalDateFormat ?? 'd-m-Y') ?? '--' }}</td>
                                <td class="py-2">{{ $payout->reference ?? 'Employee payout' }}</td>
                                <td class="py-2">
                                    @if($proofUrl)
                                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="text-teal-700 hover:text-teal-600">View/Download</a>
                                    @else
                                        <span class="text-slate-400">--</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">{{ $payout->currency ?? '' }} {{ number_format($payout->amount ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-slate-500">No payouts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 card p-4">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Advance Transactions</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm text-slate-700">
                    <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Amount</th>
                            <th class="py-2 text-left">Method</th>
                            <th class="py-2 text-left">Reference</th>
                            <th class="py-2 text-left">Scope</th>
                            <th class="py-2 text-left">Proof</th>
                            <th class="py-2 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentAdvanceTransactions as $advance)
                            @php
                                $proofPath = $advance->metadata['payment_proof_path'] ?? null;
                                $proofUrl = $proofPath ? route('admin.hr.employee-payouts.proof', $advance) : null;
                                $scope = $advance->metadata['advance_scope'] ?? null;
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="py-2 whitespace-nowrap tabular-nums">{{ $advance->paid_at?->format(($globalDateTimeFormat ?? 'd-m-Y h:i A')) ?? '--' }}</td>
                                <td class="py-2">{{ $advance->currency ?? 'BDT' }} {{ number_format((float) ($advance->amount ?? 0), 2) }}</td>
                                <td class="py-2">{{ $advance->payout_method ? ucfirst(str_replace('_', ' ', (string) $advance->payout_method)) : '--' }}</td>
                                <td class="py-2">{{ $advance->reference ?? '--' }}</td>
                                <td class="py-2">{{ $scope ? ucfirst(str_replace('_', ' ', (string) $scope)) : '--' }}</td>
                                <td class="py-2">
                                    @if($proofUrl)
                                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="text-teal-700 hover:text-teal-600">View/Download</a>
                                    @else
                                        <span class="text-slate-400">--</span>
                                    @endif
                                </td>
                                <td class="py-2">{{ $advance->note ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-slate-500">No advance transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($tab === 'projects')
        @php
            $projectStatusLabels = [
                'ongoing' => 'Ongoing',
                'hold' => 'On hold',
                'complete' => 'Completed',
                'cancel' => 'Cancelled',
            ];
            $taskStatusOrder = ['pending', 'in_progress', 'blocked', 'completed'];
        @endphp
        <div class="grid gap-4 md:grid-cols-4">
            <div class="card p-4 md:col-span-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Assigned Projects</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $projects->count() }}</div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                    @foreach($projectStatusLabels as $status => $label)
                        <span class="rounded-full border border-slate-300 bg-white px-2 py-1">
                            {{ $label }}: {{ $projectStatusCounts[$status] ?? 0 }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="mt-4 card p-6">
            <div class="text-sm font-semibold text-slate-800 mb-3">Projects</div>
            @if($projects->isEmpty())
                <div class="text-sm text-slate-500">No projects assigned to this employee.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Project</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Customer</th>
                            <th class="px-3 py-2">Assigned Tasks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($projects as $project)
                            @php
                                $taskCounts = $projectTaskStatusCounts->get($project->id, collect());
                                $taskTotal = $taskCounts->sum();
                                $extraTaskCounts = $taskCounts->except($taskStatusOrder);
                            @endphp
                            <tr class="border-t border-slate-100 align-top">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900">
                                        <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.projects.show', $project) }}">
                                            {{ $project->name }}
                                        </a>
                                    </div>
                                    <div class="text-xs text-slate-500">Project ID: {{ $project->id }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full border border-slate-300 px-2 py-0.5 text-xs font-semibold text-slate-700 bg-slate-50">
                                        {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-sm text-slate-700">
                                    {{ $project->customer?->name ?? '--' }}
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600">
                                    <div class="font-semibold text-slate-700">Assigned tasks: {{ $taskTotal }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach($taskStatusOrder as $status)
                                            <span class="rounded-full border border-slate-300 bg-white px-2 py-1">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $taskCounts[$status] ?? 0 }}
                                            </span>
                                        @endforeach
                                        @foreach($extraTaskCounts as $status => $count)
                                            <span class="rounded-full border border-slate-300 bg-white px-2 py-1">
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        <div class="card p-6 text-sm text-slate-600">
            No data available for this tab yet.
        </div>
    @endif
@endsection
