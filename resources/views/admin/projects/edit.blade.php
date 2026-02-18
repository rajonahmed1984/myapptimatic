@extends('layouts.admin')

@section('title', 'Edit Project #'.$project->id)
@section('page-title', 'Edit Project')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">Edit project</div>
            <div class="text-sm text-slate-500">Update project details, links, and budget fields.</div>
        </div>
        <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.projects.update', $project) }}" enctype="multipart/form-data" class="mt-2 space-y-6 rounded-2xl border border-slate-200 bg-white/80 p-5">
            @csrf
            @method('PUT')

            <fieldset class="space-y-4">
                <legend class="text-xs uppercase tracking-[0.2em] text-slate-400">Project Info</legend>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Project name</label>
                        <input name="name" value="{{ old('name', $project->name) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Customer</label>
                        <select name="customer_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">Select customer</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id', $project->customer_id) == $customer->id)>{{ $customer->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>



                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs text-slate-500">Type</label>
                        <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected(old('type', $project->type) === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Status</label>
                        <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $project->status) === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Start date</label>
                        <input name="start_date" type="date" value="{{ old('start_date', optional($project->start_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Expected end date</label>
                        <input name="expected_end_date" type="date" value="{{ old('expected_end_date', optional($project->expected_end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Due date (internal)</label>
                        <input name="due_date" type="date" value="{{ old('due_date', optional($project->due_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                </div>

                <div>
                    <label class="text-xs text-slate-500">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes', $project->notes) }}</textarea>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Contract file</label>
                        <input type="file" name="contract_file" accept=".pdf,.doc,.docx,image/*" class="mt-1 w-full text-xs text-slate-600">
                        @if($project->contract_file_path)
                            <a href="{{ route('admin.projects.download', ['project' => $project, 'type' => 'contract']) }}" class="mt-1 block text-xs font-semibold text-teal-700 hover:text-teal-600">
                                {{ $project->contract_original_name ?? 'Download contract' }}
                            </a>
                        @endif
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Proposal file</label>
                        <input type="file" name="proposal_file" accept=".pdf,.doc,.docx,image/*" class="mt-1 w-full text-xs text-slate-600">
                        @if($project->proposal_file_path)
                            <a href="{{ route('admin.projects.download', ['project' => $project, 'type' => 'proposal']) }}" class="mt-1 block text-xs font-semibold text-teal-700 hover:text-teal-600">
                                {{ $project->proposal_original_name ?? 'Download proposal' }}
                            </a>
                        @endif
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-xs uppercase tracking-[0.2em] text-slate-400">People</legend>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Sales representatives</label>
                        <div class="mt-2 space-y-2 rounded-2xl border border-slate-200 bg-white/80 p-3">
                            @foreach($salesReps as $rep)
                                @php
                                    $selectedSalesReps = collect(old('sales_rep_ids', $project->salesRepresentatives->pluck('id')->toArray()));
                                    $assignedRep = $project->salesRepresentatives->firstWhere('id', $rep->id);
                                    $repAmount = old('sales_rep_amounts.'.$rep->id, $assignedRep?->pivot?->amount ?? 0);
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <label class="flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" name="sales_rep_ids[]" value="{{ $rep->id }}" @checked($selectedSalesReps->contains($rep->id))>
                                        <span>{{ $rep->name }} ({{ $rep->email }})</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-slate-500">Amount</span>
                                        <input type="number" min="0" step="0.01" name="sales_rep_amounts[{{ $rep->id }}]" value="{{ $repAmount }}" class="w-28 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Amounts apply only to selected sales reps.</p>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Assign employees</label>
                        <div class="mt-2 space-y-2 rounded-2xl border border-slate-200 bg-white/80 p-3">
                            @foreach($employees as $employee)
                                @php
                                    $selectedEmployees = collect(old('employee_ids', $project->employees->pluck('id')->toArray()));
                                    $isAssigned = $selectedEmployees->contains($employee->id);
                                    $isContract = $employee->employment_type === 'contract';
                                    $assignedContractCount = $project->employees->where('employment_type', 'contract')->count();
                                    $defaultContractAmount = ($assignedContractCount === 1 && $isAssigned && $isContract)
                                        ? ($project->contract_amount ?? '')
                                        : '';
                                    $contractAmount = old('contract_employee_amounts.'.$employee->id, $defaultContractAmount);
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-3" data-employee-row>
                                    <label class="flex items-center gap-2 text-xs text-slate-600">
                                        <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" @checked($isAssigned) data-employment-type="{{ $employee->employment_type }}">
                                        <span>{{ $employee->name }} @if($employee->designation)<span class="text-slate-500">({{ $employee->designation }})</span>@endif</span>
                                    </label>
                                    @if($isContract)
                                        <div class="flex items-center gap-2 {{ $isAssigned ? '' : 'hidden' }}" data-contract-amount>
                                            <span class="text-xs text-slate-500">Amount</span>
                                            <input type="number" min="0" step="0.01" name="contract_employee_amounts[{{ $employee->id }}]" value="{{ $contractAmount }}" class="w-28 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs" @disabled(! $isAssigned)>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Select employees assigned to this project.</p>
                        <p class="mt-1 text-xs text-slate-500">Contract employee amounts apply only to selected contract employees.</p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-xs uppercase tracking-[0.2em] text-slate-400">Budget & Currency</legend>

                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="text-xs text-slate-500">Total budget</label>
                        <input name="total_budget" type="number" step="0.01" value="{{ old('total_budget', $project->total_budget) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Initial payment</label>
                        <input name="initial_payment_amount" type="number" step="0.01" value="{{ old('initial_payment_amount', $project->initial_payment_amount) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Currency</label>
                        <select name="currency" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                            @foreach($currencyOptions as $currency)
                                <option value="{{ $currency }}" @selected(old('currency', $project->currency) === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>
                <div>
                    <label class="text-xs text-slate-500">Budget (legacy)</label>
                    <input name="budget_amount" type="number" step="0.01" value="{{ old('budget_amount', $project->budget_amount) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Software overhead fee</label>
                    <input name="software_overhead" type="number" step="0.01" min="0" value="{{ old('software_overhead', $project->software_overhead) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Website overhead fee</label>
                    <input name="website_overhead" type="number" step="0.01" min="0" value="{{ old('website_overhead', $project->website_overhead) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            </fieldset>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.projects.show', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Update project</button>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const employeeRows = document.querySelectorAll('[data-employee-row]');

            const toggleContractAmount = (row) => {
                const checkbox = row.querySelector('input[type="checkbox"][data-employment-type]');
                const amountWrap = row.querySelector('[data-contract-amount]');

                if (!checkbox || !amountWrap) {
                    return;
                }

                if (checkbox.dataset.employmentType !== 'contract') {
                    return;
                }

                const shouldShow = checkbox.checked;
                const amountInput = amountWrap.querySelector('input');

                amountWrap.classList.toggle('hidden', !shouldShow);

                if (amountInput) {
                    amountInput.disabled = !shouldShow;
                    amountInput.required = shouldShow;
                    if (!shouldShow) {
                        amountInput.value = '';
                    }
                }
            };

            employeeRows.forEach((row) => {
                toggleContractAmount(row);
                const checkbox = row.querySelector('input[type="checkbox"][data-employment-type]');
                checkbox?.addEventListener('change', () => toggleContractAmount(row));
            });
        });
    </script>
@endsection
