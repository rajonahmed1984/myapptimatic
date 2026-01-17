@extends('layouts.admin')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
            <a href="{{ route('admin.projects.chat', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">
                Chat
                @php $projectChatUnreadCount = (int) ($projectChatUnreadCount ?? 0); @endphp
                <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $projectChatUnreadCount > 0 ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                    {{ $projectChatUnreadCount }}
                </span>
            </a>
            <a href="{{ route('admin.projects.edit', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300">Delete</button>
            </form>
        </div>
    </div>

    <div class="card p-6 space-y-6">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project Info</div>
            <div class="mt-3 grid gap-4 md:grid-cols-3 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Overview</div>
                    <div class="mt-2 font-semibold text-slate-900">{{ ucfirst($project->type) }}</div>
                    <div class="text-xs text-slate-500">
                        Project ID: {{ $project->id }}<br>
                        Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</div>
                    <div class="mt-2 text-xs text-slate-500">
                        Start: {{ $project->start_date?->format($globalDateFormat) ?? '--' }}<br>
                        Expected end: {{ $project->expected_end_date?->format($globalDateFormat) ?? '--' }}<br>
                        Due: {{ $project->due_date?->format($globalDateFormat) ?? '--' }}
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Description</div>
                    <div class="mt-2 text-xs text-slate-600 whitespace-pre-wrap">
                        {{ $project->description ?? 'No description provided.' }}
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">People</div>
            <div class="mt-3 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                    <div class="mt-2 font-semibold text-slate-900">{{ $project->customer?->name ?? '--' }}</div>
                    <div class="text-xs text-slate-500">Client ID: {{ $project->customer_id ?? '--' }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Team</div>
                    @php
                        $employeeNames = $project->employees->pluck('name')->filter()->implode(', ');
                        $salesRepNames = $project->salesRepresentatives
                            ->map(function ($rep) use ($project) {
                                $amount = $rep->pivot?->amount ?? 0;
                                $amountText = $amount > 0 ? ' ('.$project->currency.' '.number_format($amount, 2).')' : '';
                                return trim($rep->name . $amountText);
                            })
                            ->filter()
                            ->implode(', ');
                    @endphp
                    <div class="mt-2 text-xs text-slate-600">
                        Employees: {{ $employeeNames ?: '--' }}<br>
                        Sales reps: {{ $salesRepNames ?: '--' }}
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Budget & Currency</div>
            <div class="mt-3 grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Budget Summary</div>
                    <div class="mt-2 text-xs text-slate-600">
                        Total budget: {{ $project->total_budget !== null ? $project->currency.' '.number_format($project->total_budget, 2) : '--' }}<br>
                        Sales rep total: {{ $project->sales_rep_total !== null ? $project->currency.' '.number_format($project->sales_rep_total, 2) : '--' }}<br>
                        Remaining budget: {{ $project->remaining_budget !== null ? $project->currency.' '.number_format($project->remaining_budget, 2) : '--' }}<br>
                        Initial payment: {{ $project->initial_payment_amount !== null ? $project->currency.' '.number_format($project->initial_payment_amount, 2) : '--' }}<br>
                        Budget (legacy): {{ $project->budget_amount !== null ? $project->currency.' '.number_format($project->budget_amount, 2) : '--' }}<br>
                        Currency: {{ $project->currency ?? '--' }}
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Initial Invoice</div>
                    @if(!empty($initialInvoice))
                        <div class="mt-2 text-xs text-slate-600">
                            Number:
                            <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.invoices.show', $initialInvoice) }}">#{{ $initialInvoice->number ?? $initialInvoice->id }}</a><br>
                            Amount: {{ $initialInvoice->currency ?? $project->currency }} {{ $initialInvoice->total }}<br>
                            Status: {{ ucfirst($initialInvoice->status) }}
                        </div>
                    @else
                        <div class="mt-2 text-xs text-slate-500">No initial invoice linked.</div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Maintenance</div>
            <div class="mt-3 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-slate-800">Maintenance plans</div>
                        <a href="{{ route('admin.project-maintenances.create', ['project_id' => $project->id]) }}" class="rounded-full border border-teal-200 px-3 py-1 text-xs font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">Add maintenance</a>
                    </div>
                    @php $maintenances = $project->maintenances?->sortBy('next_billing_date') ?? collect(); @endphp
                    @if($maintenances->isEmpty())
                        <div class="mt-3 text-xs text-slate-500">No maintenance plans for this project.</div>
                    @else
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead>
                                <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th class="px-3 py-2">Title</th>
                                    <th class="px-3 py-2">Cycle</th>
                                    <th class="px-3 py-2">Next Billing</th>
                                    <th class="px-3 py-2">Status</th>
                                    <th class="px-3 py-2">Auto</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2 text-right">Invoices</th>
                                    <th class="px-3 py-2 text-right">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($maintenances as $maintenance)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-3 py-2">{{ $maintenance->title }}</td>
                                        <td class="px-3 py-2">{{ ucfirst($maintenance->billing_cycle) }}</td>
                                        <td class="px-3 py-2 text-xs text-slate-600">{{ $maintenance->next_billing_date?->format($globalDateFormat) ?? '--' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $maintenance->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($maintenance->status === 'paused' ? 'border-amber-200 text-amber-700 bg-amber-50' : 'border-slate-200 text-slate-600 bg-slate-50') }}">
                                                {{ ucfirst($maintenance->status) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-slate-600">{{ $maintenance->auto_invoice ? 'Yes' : 'No' }}</td>
                                        <td class="px-3 py-2 text-right">{{ $maintenance->currency }} {{ number_format((float) $maintenance->amount, 2) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <a href="{{ route('admin.invoices.index', ['maintenance_id' => $maintenance->id]) }}" class="text-xs font-semibold text-slate-700 hover:text-teal-600">
                                                {{ $maintenance->invoices_count ?? 0 }}
                                            </a>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <a href="{{ route('admin.project-maintenances.edit', $maintenance) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($project->notes)
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Notes</div>
                <div class="mt-2 whitespace-pre-wrap">{{ $project->notes }}</div>
            </div>
        @endif

        @can('createTask', $project)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                        <div class="text-xs text-slate-500">Dates are locked after creation.</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" class="mt-4 grid gap-3" id="addTaskForm" enctype="multipart/form-data">
                    @csrf
                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Title</label>
                            <input name="title" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Start date</label>
                            <input type="date" name="start_date" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Due date</label>
                            <input type="date" name="due_date" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-4">
                        <div>
                            <label class="text-xs text-slate-500">Task type</label>
                            <select name="task_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                @foreach($taskTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500">Priority</label>
                            <select name="priority" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                @foreach($priorityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                            <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <label class="text-xs text-slate-500">Assign to</label>
                            <select name="assignee" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">Select assignee</option>
                                @foreach($employees as $employee)
                                    <option value="employee:{{ $employee->id }}">Employee: {{ $employee->name }}</option>
                                @endforeach
                                @foreach($salesReps as $rep)
                                    <option value="sales_rep:{{ $rep->id }}">Sales Rep: {{ $rep->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <input type="hidden" name="customer_visible" value="0">
                            <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                <input type="checkbox" name="customer_visible" value="1">
                                <span>Customer visible</span>
                            </label>
                        </div>
                    </div>

                    <div id="descriptionsContainer">
                        <div class="description-group grid gap-3 md:grid-cols-4 mb-3">
                            <div class="md:col-span-3">
                                <label class="text-xs text-slate-500">Description</label>
                                <input type="text" name="descriptions[]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Add description">
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="add-description-btn rounded-full bg-teal-500 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-600 w-full">+ Add description</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" id="clearFormBtn" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-slate-300">Clear</button>
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button>
                    </div>
                </form>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const container = document.getElementById('descriptionsContainer');
                    const form = document.getElementById('addTaskForm');
                    const clearBtn = document.getElementById('clearFormBtn');

                    function addDescriptionField() {
                        const group = document.createElement('div');
                        group.className = 'description-group grid gap-3 md:grid-cols-4 mb-3';
                        group.innerHTML = `
                            <div class="md:col-span-3">
                                <input type="text" name="descriptions[]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Add description">
                            </div>
                            <div class="flex items-start gap-2">
                                <button type="button" class="add-description-btn rounded-full bg-teal-500 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-600 flex-1">+ Add</button>
                                <button type="button" class="remove-description-btn rounded-full bg-rose-500 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-600 flex-1">Remove</button>
                            </div>
                        `;
                        container.appendChild(group);
                    }

                    container.addEventListener('click', function(e) {
                        if (e.target.classList.contains('add-description-btn')) {
                            e.preventDefault();
                            addDescriptionField();
                        }
                        if (e.target.classList.contains('remove-description-btn')) {
                            e.preventDefault();
                            e.target.closest('.description-group').remove();
                        }
                    });

                    clearBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        form.reset();
                        // Reset descriptions to just one field
                        const groups = container.querySelectorAll('.description-group');
                        if (groups.length > 1) {
                            groups.forEach((group, index) => {
                                if (index > 0) group.remove();
                            });
                        }
                    });
                });
            </script>
        @endcan

        @if($tasks->count() > 0)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2">Assignee</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Progress</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody id="tasksTableBody">
                        @foreach($tasks as $task)
                            @include('admin.projects.partials.task-row', [
                                'task' => $task,
                                'project' => $project,
                                'taskTypeOptions' => $taskTypeOptions,
                            ])
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($tasks->hasPages())
                    <div class="mt-4">
                        {{ $tasks->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const addTaskForm = document.getElementById('addTaskForm');
            const tasksBody = document.getElementById('tasksTableBody');

            const parseError = async (response) => {
                try {
                    const data = await response.json();
                    if (data?.message) {
                        return data.message;
                    }
                    if (data?.errors) {
                        const first = Object.values(data.errors).flat()[0];
                        if (first) return first;
                    }
                } catch (error) {
                    return null;
                }
                return null;
            };

            const submitTaskForm = async (form, onSuccess) => {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                if (!response.ok) {
                    const message = await parseError(response);
                    alert(message || 'Task update failed.');
                    return;
                }

                const payload = await response.json();
                if (!payload?.ok) {
                    alert(payload?.message || 'Task update failed.');
                    return;
                }

                onSuccess(payload);
            };

            if (addTaskForm) {
                addTaskForm.addEventListener('submit', (event) => {
                    event.preventDefault();

                    submitTaskForm(addTaskForm, (payload) => {
                        const rowHtml = payload?.data?.row_html;
                        if (!rowHtml) {
                            location.reload();
                            return;
                        }

                        if (!tasksBody) {
                            location.reload();
                            return;
                        }

                        tasksBody.insertAdjacentHTML('beforeend', rowHtml);
                        addTaskForm.reset();

                        const groups = document.querySelectorAll('#descriptionsContainer .description-group');
                        if (groups.length > 1) {
                            groups.forEach((group, index) => {
                                if (index > 0) group.remove();
                            });
                        }
                    });
                });
            }

            if (tasksBody) {
                tasksBody.addEventListener('submit', (event) => {
                    const form = event.target;
                    if (!form.classList.contains('task-update-form')) {
                        return;
                    }

                    event.preventDefault();
                    submitTaskForm(form, (payload) => {
                        const rowHtml = payload?.data?.row_html;
                        const taskId = payload?.data?.task_id;
                        if (!rowHtml || !taskId) {
                            location.reload();
                            return;
                        }

                        const row = document.getElementById(`task-row-${taskId}`);
                        if (!row) {
                            location.reload();
                            return;
                        }

                        row.outerHTML = rowHtml;
                    });
                });
            }
        });
    </script>
@endsection
