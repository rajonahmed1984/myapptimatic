@extends('layouts.client')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Project</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
    </div>

    <div class="card p-6">
        <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project ID</div>
                <div class="mt-2 font-semibold text-slate-900">#{{ $project->id }}</div>
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
                    Initial payment: {{ $project->initial_payment_amount ? $project->currency.' '.$project->initial_payment_amount : '--' }}
                </div>
                @if(!empty($initialInvoice))
                    <div class="mt-2 text-xs text-slate-500">
                        Initial invoice: #{{ $initialInvoice->number ?? $initialInvoice->id }} ({{ ucfirst($initialInvoice->status) }})
                        <a href="{{ route('client.invoices.show', $initialInvoice) }}" class="text-teal-700 hover:text-teal-600">View invoice</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                    <div class="text-xs text-slate-500">Dates and assignment are managed internally and locked after creation.</div>
                </div>
            </div>
            <form method="POST" action="{{ route('client.projects.tasks.store', $project) }}" class="mt-4 grid gap-3 md:grid-cols-3" enctype="multipart/form-data">
                @csrf
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Title</label>
                    <input name="title" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Description</label>
                    <input name="description" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-1">
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
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                    <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button>
                </div>
            </form>
        </div>

        @if($tasks && $tasks->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks (customer-visible)</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2">Status</th>
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
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600">
                                    Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }}<br>
                                    Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }}
                                </td>
                                <td class="px-3 py-2">
                                    <form method="POST" action="{{ route('client.projects.tasks.update', [$project, $task]) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                                            @foreach(['pending','in_progress','blocked','completed'] as $status)
                                                <option value="{{ $status }}" @selected($task->status === $status)>{{ ucfirst(str_replace('_',' ', $status)) }}</option>
                        @endforeach
                    </select>
                    <textarea name="description" rows="2" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs" placeholder="Description">{{ $task->description }}</textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-800">Update</button>
                    </div>
                </form>
            </td>
            <td class="px-3 py-2 text-right align-top text-xs text-slate-500">
                <div>Status updates only; dates/assignees are fixed.</div>
                <a href="{{ route('client.projects.tasks.show', [$project, $task]) }}" class="mt-2 inline-flex text-xs font-semibold text-teal-600 hover:text-teal-500">Open Task</a>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
