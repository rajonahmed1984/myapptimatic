@extends('layouts.rep')

@section('title', 'Project #'.$project->id)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
    </div>

    @php
        $stats = $taskStats ?? ['total' => 0, 'in_progress' => 0, 'completed' => 0, 'unread' => 0];
    @endphp
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['total'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Total Tasks</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['in_progress'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">In Progress</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['completed'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Completed</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['unread'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Unread</div>
        </div>
    </div>

    <div class="grid gap-6">
        <div class="card p-6">
            <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                    <div class="mt-2 font-semibold text-slate-900">{{ $project->customer?->name ?? '--' }}</div>
                    <div class="text-xs text-slate-500">Project ID: {{ $project->id }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</div>
                    <div class="mt-2 text-sm text-slate-700">
                        Start: {{ $project->start_date?->format($globalDateFormat) ?? '--' }}<br>
                        Expected end: {{ $project->expected_end_date?->format($globalDateFormat) ?? '--' }}<br>
                        Due: {{ $project->due_date?->format($globalDateFormat) ?? '--' }}
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Financials</div>
                    <div class="mt-2 text-sm text-slate-700">
                        Budget: {{ $project->total_budget ? $project->currency.' '.$project->total_budget : '--' }}<br>
                        Sales rep amount: {{ $salesRepAmount !== null ? $project->currency.' '.number_format($salesRepAmount, 2) : '--' }}<br>
                        Initial payment: {{ $project->initial_payment_amount ? $project->currency.' '.$project->initial_payment_amount : '--' }}
                    </div>
                    @if(!empty($initialInvoice))
                        <div class="mt-2 text-xs text-slate-500">
                            Initial invoice: #{{ $initialInvoice->number ?? $initialInvoice->id }} ({{ ucfirst($initialInvoice->status) }})
                        </div>
                    @endif
                </div>
            </div>

            @if(!empty($maintenances) && $maintenances->isNotEmpty())
                <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Maintenance</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                            <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th class="px-3 py-2">Title</th>
                                <th class="px-3 py-2">Cycle</th>
                                <th class="px-3 py-2">Next Billing</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                                <th class="px-3 py-2 text-right">Invoices</th>
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
                                    <td class="px-3 py-2 text-right">{{ $maintenance->currency }} {{ number_format((float) $maintenance->amount, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $maintenance->invoices?->count() ?? 0 }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                        <div class="text-xs text-slate-500">Task start and due dates are locked after creation.</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('rep.projects.tasks.store', $project) }}" class="grid gap-3 md:grid-cols-6" enctype="multipart/form-data">
                    @csrf
                    <div class="md:col-span-3">
                        <label class="text-xs text-slate-500">Title</label>
                        <input name="title" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Task type</label>
                        <select name="task_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                            @foreach($taskTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <label class="text-xs text-slate-500">Priority</label>
                        <select name="priority" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            @foreach($priorityOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-6">
                        <label class="text-xs text-slate-500">Description</label>
                        <input name="description" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Start date</label>
                        <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Due date</label>
                        <input type="date" name="due_date" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                        <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="customer_visible" value="0">
                        <input type="checkbox" name="customer_visible" value="1">
                        <span class="text-xs text-slate-600">Customer visible</span>
                    </div>
                    <div class="md:col-span-6 flex justify-end">
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button>
                    </div>
                </form>
            </div>

            @if($tasks && $tasks->isNotEmpty())
                <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                            <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th class="px-3 py-2">Task</th>
                                <th class="px-3 py-2">Dates</th>
                                <th class="px-3 py-2">Progress</th>
                                <th class="px-3 py-2 text-right">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($tasks as $task)
                                <tr class="border-t border-slate-100 align-top">
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-slate-900">{{ $task->title }}</div>
                                        <div class="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                            {{ $taskTypeOptions[$task->task_type] ?? ucfirst($task->task_type ?? 'Task') }}
                                        </div>
                                        @if($task->description)
                                            <div class="text-xs text-slate-500">{{ $task->description }}</div>
                                        @endif
                                        @if($task->customer_visible)
                                            <div class="text-[11px] text-emerald-600 font-semibold">Customer visible</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-600">
                                        <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Dates locked</div>
                                        Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }}<br>
                                        Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500 text-right align-top">
                                        Progress: {{ $task->progress ?? 0 }}%
                                        @if($task->completed_at)
                                            <div>Completed at {{ $task->completed_at->format($globalDateFormat) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right align-top">
                                        <a href="{{ route('rep.projects.tasks.show', [$project, $task]) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Open Task</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection
