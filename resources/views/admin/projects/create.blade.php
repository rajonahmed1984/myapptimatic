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
        <form method="POST" action="{{ route('admin.projects.store') }}" class="mt-2 grid gap-4 rounded-2xl border border-slate-200 bg-white/80 p-5">
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
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Order (optional)</label>
                    <select name="order_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}" @selected(old('order_id') == $order->id)>{{ $order->order_number ?? $order->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Invoice (optional)</label>
                    <select name="invoice_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected(old('invoice_id') == $invoice->id)>{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Subscription (optional)</label>
                    <select name="subscription_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($subscriptions as $subscription)
                            <option value="{{ $subscription->id }}" @selected(old('subscription_id') == $subscription->id)>Subscription #{{ $subscription->id }}</option>
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
                    <select name="sales_rep_ids[]" multiple class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($salesReps as $rep)
                            <option value="{{ $rep->id }}" @selected(collect(old('sales_rep_ids', []))->contains($rep->id))>{{ $rep->name }} ({{ $rep->email }})</option>
                        @endforeach
                    </select>
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
                    <select name="employee_ids[]" multiple class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(collect(old('employee_ids', []))->contains($employee->id))>{{ $employee->name }} {{ $employee->designation ? "({$employee->designation})" : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs text-slate-500">Description</label>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="text-xs text-slate-500">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes') }}</textarea>
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
                    <input name="currency" value="{{ old('currency', $defaultCurrency) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Budget (legacy)</label>
                    <input name="budget_amount" type="number" step="0.01" value="{{ old('budget_amount') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
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
                    @php $taskOld = old('tasks', [ ['title' => '', 'descriptions' => [''], 'start_date' => '', 'due_date' => '', 'assignee' => '', 'customer_visible' => false] ]); @endphp
                    @foreach($taskOld as $index => $task)
                        <div class="rounded-xl border border-slate-100 bg-white p-3 task-row" data-index="{{ $index }}">
                            <div class="grid gap-3 md:grid-cols-4 mb-3">
                                <div class="md:col-span-2">
                                    <label class="text-xs text-slate-500">Title</label>
                                    <input name="tasks[{{ $index }}][title]" value="{{ $task['title'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm task-title" required>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Start date</label>
                                    <input type="date" name="tasks[{{ $index }}][start_date]" value="{{ $task['start_date'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500">Due date</label>
                                    <input type="date" name="tasks[{{ $index }}][due_date]" value="{{ $task['due_date'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3 mb-3">
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
                                    <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                        <input type="hidden" name="tasks[{{ $index }}][customer_visible]" value="0">
                                        <input type="checkbox" name="tasks[{{ $index }}][customer_visible]" value="1" @checked(!empty($task['customer_visible']))>
                                        <span>Customer visible</span>
                                    </label>
                                </div>
                            </div>

                            <div class="descriptions-container mb-3" data-task-index="{{ $index }}">
                                @php 
                                    $descriptions = is_array($task['descriptions'] ?? null) ? $task['descriptions'] : (isset($task['description']) && $task['description'] ? [$task['description']] : ['']):
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
                            <input type="date" name="tasks[${index}][start_date]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Due date</label>
                            <input type="date" name="tasks[${index}][due_date]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
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
                            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                <input type="hidden" name="tasks[${index}][customer_visible]" value="0">
                                <input type="checkbox" name="tasks[${index}][customer_visible]" value="1">
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
        });
    </script>
@endsection
