@extends('layouts.admin')

@section('title', 'New Project')
@section('page-title', 'New Project')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">Create project</div>
            <div class="text-sm text-slate-500">Link to orders/invoices, assign teams, and create initial tasks.</div>
        </div>
        <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to projects</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.projects.store') }}" hx-boost="false" class="mt-2 grid gap-4 rounded-2xl border border-slate-200 bg-white/80 p-5" enctype="multipart/form-data">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Project name</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Customer</label>
                    <select name="customer_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Select customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->display_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>



            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Type</label>
                    <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(old('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Sales representatives</label>
                    <div class="mt-2 space-y-2 rounded-2xl border border-slate-200 bg-white/80 p-3">
                        @foreach($salesReps as $rep)
                            @php
                                $selectedSalesReps = collect(old('sales_rep_ids', []));
                                $repAmount = old('sales_rep_amounts.'.$rep->id, 0);
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
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Start date</label>
                    <input name="start_date" type="date" value="{{ old('start_date') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Expected end date</label>
                    <input name="expected_end_date" type="date" value="{{ old('expected_end_date') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Due date (internal)</label>
                    <input name="due_date" type="date" value="{{ old('due_date') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Assign employees</label>
                    <div class="mt-2 space-y-2 rounded-2xl border border-slate-200 bg-white/80 p-3">
                        @foreach($employees as $employee)
                            @php
                                $selectedEmployees = collect(old('employee_ids', []));
                                $isAssigned = $selectedEmployees->contains($employee->id);
                                $isContract = $employee->employment_type === 'contract';
                                $contractAmount = old('contract_employee_amounts.'.$employee->id, '');
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

            <div>
                <label class="text-xs text-slate-500">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Contract file</label>
                    <input type="file" name="contract_file" accept=".pdf,.doc,.docx,image/*" class="mt-1 w-full text-xs text-slate-600">
                    <p class="mt-1 text-xs text-slate-500">Optional upload for signed contract.</p>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Proposal file</label>
                    <input type="file" name="proposal_file" accept=".pdf,.doc,.docx,image/*" class="mt-1 w-full text-xs text-slate-600">
                    <p class="mt-1 text-xs text-slate-500">Optional upload for proposal.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/60 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="section-label">Add Maintenance Plan</div>
                        <div class="text-sm text-slate-600">Optional recurring billing tied to this project.</div>
                    </div>
                    <button type="button" id="addMaintenanceRow" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Add plan</button>
                </div>
                <div id="maintenanceRows" class="mt-4 space-y-3">
                    @php $maintenanceOld = old('maintenances', []); @endphp
                    @forelse($maintenanceOld as $index => $maintenance)
                        <div class="maintenance-row grid gap-3 md:grid-cols-6 mb-2" data-index="{{ $index }}">
                            <div class="md:col-span-2">
                                <label class="text-xs text-slate-500">Title</label>
                                <input name="maintenances[{{ $index }}][title]" value="{{ $maintenance['title'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Amount</label>
                                <input name="maintenances[{{ $index }}][amount]" type="number" min="0.01" step="0.01" value="{{ $maintenance['amount'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Billing cycle</label>
                                <select name="maintenances[{{ $index }}][billing_cycle]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                    <option value="monthly" @selected(($maintenance['billing_cycle'] ?? '') === 'monthly')>Monthly</option>
                                    <option value="yearly" @selected(($maintenance['billing_cycle'] ?? '') === 'yearly')>Yearly</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Start date</label>
                                <input name="maintenances[{{ $index }}][start_date]" type="date" value="{{ $maintenance['start_date'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                            </div>
                            <div class="flex items-end justify-between gap-2">
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                        <input type="hidden" name="maintenances[{{ $index }}][auto_invoice]" value="0">
                                        <input type="checkbox" name="maintenances[{{ $index }}][auto_invoice]" value="1" @checked(!isset($maintenance['auto_invoice']) || $maintenance['auto_invoice'])>
                                        <span>Auto invoice</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                        <input type="hidden" name="maintenances[{{ $index }}][sales_rep_visible]" value="0">
                                        <input type="checkbox" name="maintenances[{{ $index }}][sales_rep_visible]" value="1" @checked(!empty($maintenance['sales_rep_visible']))>
                                        <span>Sales rep visible</span>
                                    </label>
                                </div>
                                <button type="button" class="remove-maintenance-row rounded-full border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove</button>
                            </div>
                        </div>
                    @empty
                        <div id="maintenanceEmpty" class="text-xs text-slate-500">No maintenance plans added.</div>
                    @endforelse
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <label class="text-xs text-slate-500">Total budget</label>
                    <input name="total_budget" type="number" step="0.01" value="{{ old('total_budget') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Initial payment</label>
                    <input name="initial_payment_amount" type="number" step="0.01" value="{{ old('initial_payment_amount') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Currency</label>
                    <select name="currency" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                        @foreach($currencyOptions as $currency)
                            <option value="{{ $currency }}" @selected(old('currency', $defaultCurrency) === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Budget (legacy)</label>
                    <input name="budget_amount" type="number" step="0.01" value="{{ old('budget_amount') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/60 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="section-label">Overhead fees (optional)</div>
                        <div class="text-sm text-slate-600">Add per-project overhead line items.</div>
                    </div>
                    <button type="button" id="addOverheadRow" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Add fee</button>
                </div>
                <div id="overheadRows" class="mt-4 space-y-3">
                    @php
                        $overheadsOld = old('overheads', [['short_details' => '', 'amount' => '']]);
                    @endphp
                    @foreach($overheadsOld as $index => $overhead)
                        <div class="overhead-row grid gap-3 md:grid-cols-3 items-end" data-index="{{ $index }}">
                            <div class="md:col-span-2">
                                <label class="text-xs text-slate-500">Details</label>
                                <input name="overheads[{{ $index }}][short_details]" value="{{ $overhead['short_details'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="e.g. Payment gateway setup">
                            </div>
                            <div>
                                <label class="text-xs text-slate-500">Amount</label>
                                <div class="flex items-center gap-2">
                                    <input name="overheads[{{ $index }}][amount]" type="number" step="0.01" min="0" value="{{ $overhead['amount'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    <button type="button" class="remove-overhead-row rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Planned hours</label>
                    <input name="planned_hours" type="number" step="0.01" value="{{ old('planned_hours') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Hourly cost</label>
                    <input name="hourly_cost" type="number" step="0.01" value="{{ old('hourly_cost') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Actual hours</label>
                    <input name="actual_hours" type="number" step="0.01" value="{{ old('actual_hours') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/60 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="section-label">Initial tasks</div>
                        <div class="text-sm text-slate-600">Add at least one task with dates and assignee.</div>
                    </div>
                    <button type="button" id="addTaskRow" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Add task</button>
                </div>
                <div id="taskRows" class="mt-4 space-y-3">
                    @php $taskOld = old('tasks', [ ['title' => '', 'task_type' => 'feature', 'priority' => 'medium', 'descriptions' => [''], 'start_date' => now()->toDateString(), 'due_date' => '', 'assignee' => '', 'customer_visible' => false] ]); @endphp
                    @foreach($taskOld as $index => $task)
                        <div class="rounded-xl border border-slate-100 bg-white p-3 task-row" data-index="{{ $index }}">
                            <div class="grid gap-3 md:grid-cols-5 mb-3">
                                <div class="md:col-span-2">
                                    <label class="text-xs text-slate-500">Title</label>
                                    <input name="tasks[{{ $index }}][title]" value="{{ $task['title'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm task-title" required>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Task type</label>
                                    <select name="tasks[{{ $index }}][task_type]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                        @foreach($taskTypeOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(($task['task_type'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Priority</label>
                                    <select name="tasks[{{ $index }}][priority]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                        @foreach($priorityOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(($task['priority'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Start date</label>
                                    <input type="date" name="tasks[{{ $index }}][start_date]" value="{{ $task['start_date'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Due date</label>
                                    <input type="date" name="tasks[{{ $index }}][due_date]" value="{{ $task['due_date'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-xs text-slate-500">Assign to</label>
                                    <select name="tasks[{{ $index }}][assignee]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                        <option value="">Select assignee</option>
                                        @foreach($employees as $employee)
                                            @php $val = 'employee:'.$employee->id; @endphp
                                            <option value="{{ $val }}" @selected(($task['assignee'] ?? '') === $val)>Employee: {{ $employee->name }}</option>
                                        @endforeach
                                        @foreach($salesReps as $rep)
                                            @php $val = 'sales_rep:'.$rep->id; @endphp
                                            <option value="{{ $val }}" @selected(($task['assignee'] ?? '') === $val)>Sales Rep: {{ $rep->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <label class="mt-1 flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 cursor-pointer focus-within:border-teal-300 focus-within:ring-2 focus-within:ring-teal-100">
                                        <input type="hidden" name="tasks[{{ $index }}][customer_visible]" value="0">
                                        <input type="checkbox" name="tasks[{{ $index }}][customer_visible]" value="1" @checked(!empty($task['customer_visible'])) class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-2 focus:ring-teal-200 focus:ring-offset-0">
                                        <span>Customer visible</span>
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                                    <input type="file" name="tasks[{{ $index }}][attachment]" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-4 mb-3">
                                
                                
                            </div>

                            <div class="grid gap-3 md:grid-cols-3 mb-3">
                                
                            </div>

                            <div class="descriptions-container mb-3" data-task-index="{{ $index }}">
                                @php 
                                    $descriptions = is_array($task['descriptions'] ?? null) ? $task['descriptions'] : (isset($task['description']) && $task['description'] ? [$task['description']] : ['']);
                                @endphp
                                @foreach($descriptions as $descIndex => $description)
                                    <div class="description-group grid gap-3 md:grid-cols-4 mb-2">
                                        <div class="md:col-span-3">
                                            <label class="text-xs text-slate-500">{{ $descIndex === 0 ? 'Description' : '' }}</label>
                                            <input type="text" name="tasks[{{ $index }}][descriptions][]" value="{{ $description ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Add description">
                                        </div>
                                        <div class="flex items-end gap-1">
                                            <button type="button" class="add-task-description-btn rounded-full bg-teal-500 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-600 flex-1">+ Add</button>
                                            @if(count($descriptions) > 1 || $description)
                                                <button type="button" class="remove-task-description-btn rounded-full bg-rose-500 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-600 flex-1">Remove</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex justify-end">
                                <button type="button" class="remove-task-row rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove task</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save project</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const taskRows = document.getElementById('taskRows');
            const addBtn = document.getElementById('addTaskRow');
            const maintenanceRows = document.getElementById('maintenanceRows');
            const addMaintenanceBtn = document.getElementById('addMaintenanceRow');
            const employeeRows = document.querySelectorAll('[data-employee-row]');
            const defaultStartDate = @json(now()->toDateString());

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

            // Handle description field addition for existing and new tasks
            function setupDescriptionHandlers(taskRow) {
                const descContainer = taskRow.querySelector('.descriptions-container');
                const taskIndex = taskRow.dataset.index;

                descContainer.addEventListener('click', (e) => {
                    if (e.target.classList.contains('add-task-description-btn')) {
                        e.preventDefault();
                        const group = document.createElement('div');
                        group.className = 'description-group grid gap-3 md:grid-cols-4 mb-2';
                        group.innerHTML = `
                            <div class="md:col-span-3">
                                <input type="text" name="tasks[${taskIndex}][descriptions][]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Add description">
                            </div>
                            <div class="flex items-start gap-1">
                                <button type="button" class="add-task-description-btn rounded-full bg-teal-500 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-600 flex-1">+ Add</button>
                                <button type="button" class="remove-task-description-btn rounded-full bg-rose-500 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-600 flex-1">Remove</button>
                            </div>
                        `;
                        descContainer.appendChild(group);
                    }
                    if (e.target.classList.contains('remove-task-description-btn')) {
                        e.preventDefault();
                        e.target.closest('.description-group').remove();
                    }
                });
            }

            // Setup handlers for existing tasks
            document.querySelectorAll('.task-row').forEach(taskRow => {
                setupDescriptionHandlers(taskRow);
            });

            addBtn?.addEventListener('click', () => {
                const index = taskRows.children.length;
                const wrapper = document.createElement('div');
                wrapper.className = 'rounded-xl border border-slate-100 bg-white p-3 task-row';
                wrapper.dataset.index = index;
                wrapper.innerHTML = `
                    <div class="grid gap-3 md:grid-cols-4 mb-3">
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Title</label>
                            <input name="tasks[${index}][title]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm task-title" required>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Start date</label>
                            <input type="date" name="tasks[${index}][start_date]" value="${defaultStartDate}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Due date</label>
                            <input type="date" name="tasks[${index}][due_date]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-4 mb-3">
                        <div>
                            <label class="text-xs text-slate-500">Task type</label>
                            <select name="tasks[${index}][task_type]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                @foreach($taskTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Priority</label>
                            <select name="tasks[${index}][priority]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                @foreach($priorityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                            <input type="file" name="tasks[${index}][attachment]" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3 mb-3">
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Assign to</label>
                            <select name="tasks[${index}][assignee]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                <option value="">Select assignee</option>
                                @foreach($employees as $employee)
                                    <option value="employee:{{ $employee->id }}">Employee: {{ $employee->name }}</option>
                                @endforeach
                                @foreach($salesReps as $rep)
                                    <option value="sales_rep:{{ $rep->id }}">Sales Rep: {{ $rep->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="mt-1 flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 cursor-pointer focus-within:border-teal-300 focus-within:ring-2 focus-within:ring-teal-100">
                                <input type="hidden" name="tasks[${index}][customer_visible]" value="0">
                                <input type="checkbox" name="tasks[${index}][customer_visible]" value="1" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-2 focus:ring-teal-200 focus:ring-offset-0">
                                <span>Customer visible</span>
                            </label>
                        </div>
                    </div>

                    <div class="descriptions-container mb-3" data-task-index="${index}">
                        <div class="description-group grid gap-3 md:grid-cols-4 mb-2">
                            <div class="md:col-span-3">
                                <label class="text-xs text-slate-500">Description</label>
                                <input type="text" name="tasks[${index}][descriptions][]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Add description">
                            </div>
                            <div class="flex items-end gap-1">
                                <button type="button" class="add-task-description-btn rounded-full bg-teal-500 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-600 flex-1">+ Add</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" class="remove-task-row rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove task</button>
                    </div>
                `;
                taskRows.appendChild(wrapper);
                setupDescriptionHandlers(wrapper);
            });

            // Handle task row removal
            taskRows.addEventListener('click', (e) => {
                if (e.target.classList.contains('remove-task-row')) {
                    e.preventDefault();
                    if (taskRows.children.length > 1) {
                        e.target.closest('.task-row').remove();
                    } else {
                        alert('At least one task is required.');
                    }
                }
            });

            function addMaintenanceRow() {
                const index = maintenanceRows.querySelectorAll('.maintenance-row').length;
                const row = document.createElement('div');
                row.className = 'maintenance-row grid gap-3 md:grid-cols-6 mb-2';
                row.dataset.index = index;
                row.innerHTML = `
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Title</label>
                        <input name="maintenances[${index}][title]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Amount</label>
                        <input name="maintenances[${index}][amount]" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Billing cycle</label>
                        <select name="maintenances[${index}][billing_cycle]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Start date</label>
                        <input name="maintenances[${index}][start_date]" type="date" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                    </div>
                    <div class="flex items-end justify-between gap-2">
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                <input type="hidden" name="maintenances[${index}][auto_invoice]" value="0">
                                <input type="checkbox" name="maintenances[${index}][auto_invoice]" value="1" checked>
                                <span>Auto invoice</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                <input type="hidden" name="maintenances[${index}][sales_rep_visible]" value="0">
                                <input type="checkbox" name="maintenances[${index}][sales_rep_visible]" value="1">
                                <span>Sales rep visible</span>
                            </label>
                        </div>
                        <button type="button" class="remove-maintenance-row rounded-full border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Remove</button>
                    </div>
                `;
                maintenanceRows.appendChild(row);
                const emptyLabel = document.getElementById('maintenanceEmpty');
                if (emptyLabel) {
                    emptyLabel.remove();
                }
            }

            addMaintenanceBtn?.addEventListener('click', (e) => {
                e.preventDefault();
                addMaintenanceRow();
            });

            maintenanceRows?.addEventListener('click', (e) => {
                if (e.target.classList.contains('remove-maintenance-row')) {
                    e.preventDefault();
                    e.target.closest('.maintenance-row').remove();
                    if (maintenanceRows.querySelectorAll('.maintenance-row').length === 0) {
                        const empty = document.createElement('div');
                        empty.id = 'maintenanceEmpty';
                        empty.className = 'text-xs text-slate-500';
                        empty.textContent = 'No maintenance plans added.';
                        maintenanceRows.appendChild(empty);
                    }
                }
            });
        });
    </script>
@endsection
