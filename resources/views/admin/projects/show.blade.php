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
            <a href="{{ route('admin.projects.edit', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300">Delete</button>
            </form>
        </div>
    </div>

    <div class="card p-6">
        <div class="mt-6 grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                <div class="mt-2 font-semibold text-slate-900">{{ $project->customer?->name ?? '--' }}</div>
                <div class="text-xs text-slate-500">Project ID: {{ $project->id }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Type & Dates</div>
                <div class="mt-2 font-semibold text-slate-900">{{ ucfirst($project->type) }}</div>
                <div class="text-xs text-slate-500">
                    Start: {{ $project->start_date?->format($globalDateFormat) ?? '--' }}<br>
                    Expected end: {{ $project->expected_end_date?->format($globalDateFormat) ?? '--' }}<br>
                    Due: {{ $project->due_date?->format($globalDateFormat) ?? '--' }}
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Financials</div>
                <div class="mt-2 text-sm text-slate-700">
                    Budget: {{ $project->total_budget ? $project->currency.' '.$project->total_budget : '--' }}<br>
                    Initial payment: {{ $project->initial_payment_amount ? $project->currency.' '.$project->initial_payment_amount : '--' }}
                </div>
                @if(!empty($initialInvoice))
                    <div class="mt-3 rounded-xl border border-slate-200 bg-white/70 p-3 text-xs text-slate-600">
                        <div class="font-semibold text-slate-800">Initial Invoice</div>
                        <div>Number:
                            <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.invoices.show', $initialInvoice) }}">#{{ $initialInvoice->number ?? $initialInvoice->id }}</a>
                        </div>
                        <div>Amount: {{ $initialInvoice->currency ?? $project->currency }} {{ $initialInvoice->total }}</div>
                        <div>Status: {{ ucfirst($initialInvoice->status) }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Description</div>
            <div class="mt-2">{{ $project->description ?? 'No description provided.' }}</div>
        </div>

        @if($project->notes)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Notes</div>
                <div class="mt-2">{{ $project->notes }}</div>
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
                <form method="POST" action="{{ route('admin.projects.tasks.store', $project) }}" class="mt-4 grid gap-3" id="addTaskForm">
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

        @if(!empty($tasks) && $tasks->isNotEmpty())
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
                        <tbody>
                        @foreach($tasks as $task)
                            <tr class="border-t border-slate-100 align-top">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900">{{ $task->title }}</div>
                                    @if($task->description)
                                        <div class="text-xs text-slate-500">{{ $task->description }}</div>
                                    @endif
                                    @if($task->customer_visible)
                                        <div class="text-[11px] text-emerald-600 font-semibold">Customer visible</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600">
                                    Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }}<br>
                                    Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }}
                                </td>
                                <td class="px-3 py-2 text-sm text-slate-700">
                                    @if($task->assigned_type === 'employee')
                                        Employee #{{ $task->assigned_id }}
                                    @elseif($task->assigned_type === 'sales_rep')
                                        Sales Rep #{{ $task->assigned_id }}
                                    @else
                                        --
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @can('update', $task)
                                        <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $task]) }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                                                @foreach(['pending','in_progress','blocked','completed'] as $status)
                                                    <option value="{{ $status }}" @selected($task->status === $status)>{{ ucfirst(str_replace('_',' ', $status)) }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" name="progress" min="0" max="100" value="{{ $task->progress ?? 0 }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                                            <label class="flex items-center gap-2 text-xs text-slate-600">
                                                <input type="hidden" name="customer_visible" value="0">
                                                <input type="checkbox" name="customer_visible" value="1" @checked($task->customer_visible)>
                                                <span>Customer visible</span>
                                            </label>
                                            <div class="bg-slate-50 p-2 rounded-lg border border-slate-200">
                                                <div class="text-xs font-semibold text-slate-600 mb-1">Description</div>
                                                @if($task->description)
                                                    <div class="text-xs text-slate-700 whitespace-pre-wrap">{{ $task->description }}</div>
                                                @else
                                                    <div class="text-xs text-slate-400">No description</div>
                                                @endif
                                            </div>
                                            <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs" placeholder="Notes (dates are locked)">{{ $task->notes }}</textarea>
                                            <div class="flex justify-between items-center">
                                                <button type="submit" class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-800">Update</button>
                                                @can('delete', $task)
                                                    <button type="submit" form="delete-task-{{ $task->id }}" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Delete</button>
                                                @endcan
                                            </div>
                                        </form>
                                        @can('delete', $task)
                                            <form id="delete-task-{{ $task->id }}" method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $task]) }}" onsubmit="return confirm('Delete this task?');" class="hidden">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        @endcan
                                    @else
                                        <div class="text-sm">{{ ucfirst(str_replace('_',' ', $task->status)) }}</div>
                                        <div class="text-xs text-slate-500">Progress: {{ $task->progress ?? 0 }}%</div>
                                    @endcan
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-500 text-right align-top">
                                    @if($task->completed_at)
                                        Completed at {{ $task->completed_at->format($globalDateFormat) }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right align-top"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
