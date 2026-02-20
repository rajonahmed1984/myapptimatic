@extends('layouts.admin')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    @php
        $chatMeta = $chatMeta ?? null;
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
        @if($chatMeta)
            <a href="{{ route('employee.projects.chat', $project) }}" class="inline-flex items-center rounded-full border border-teal-200 bg-white px-4 py-2 text-xs font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-600">
                Open full chat
            </a>
        @endif
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

    <div class="space-y-6">
        <div class="card p-6">
        <div class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</div>
                <div class="mt-2 text-sm text-slate-700">
                    Start: {{ $project->start_date?->format($globalDateFormat) ?? '--' }}<br>
                    Expected end: {{ $project->expected_end_date?->format($globalDateFormat) ?? '--' }}<br>
                    Due: {{ $project->due_date?->format($globalDateFormat) ?? '--' }}
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Status</div>
                <div class="mt-2 text-sm text-slate-700">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
                </div>
            </div>
        </div>

        @can('createTask', $project)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                        <div class="text-xs text-slate-500">Task start and due dates are locked after creation.</div>
                    </div>
                </div>
            <form method="POST" action="{{ route('employee.projects.tasks.store', $project) }}" class="mt-4 grid gap-3 md:grid-cols-6" enctype="multipart/form-data">
                @csrf
                <div class="md:col-span-4">
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
        @endcan

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
                            @php
                                $currentStatus = $task->status ?? 'pending';
                                $statusLabels = [
                                    'pending' => 'Open',
                                    'todo' => 'Open',
                                    'in_progress' => 'In Progress',
                                    'blocked' => 'Blocked',
                                    'completed' => 'Completed',
                                    'done' => 'Completed',
                                ];
                                $statusClasses = [
                                    'pending' => 'bg-slate-100 text-slate-600',
                                    'todo' => 'bg-slate-100 text-slate-600',
                                    'in_progress' => 'bg-amber-100 text-amber-700',
                                    'blocked' => 'bg-rose-100 text-rose-700',
                                    'completed' => 'bg-emerald-100 text-emerald-700',
                                    'done' => 'bg-emerald-100 text-emerald-700',
                                ];
                                $statusLabel = $statusLabels[$currentStatus] ?? ucfirst(str_replace('_', ' ', $currentStatus));
                                $statusClass = $statusClasses[$currentStatus] ?? 'bg-slate-100 text-slate-600';
                                $currentUser = auth()->user();
                                $employeeId = $currentUser?->employee?->id;
                                $hasSubtasks = (int) ($task->subtasks_count ?? 0) > 0;
                                $isAssigned = $employeeId && (
                                    ($task->assigned_type === 'employee' && (int) $task->assigned_id === (int) $employeeId)
                                    || ($task->assignee_id && (int) $task->assignee_id === (int) ($currentUser?->id))
                                    || $task->assignments
                                        ->where('assignee_type', 'employee')
                                        ->pluck('assignee_id')
                                        ->map(fn ($id) => (int) $id)
                                        ->contains((int) $employeeId)
                                );
                                $canChangeStatus = $currentUser?->isMasterAdmin()
                                    || $isAssigned
                                    || ($task->created_by
                                        && $currentUser
                                        && $task->created_by === $currentUser->id
                                        && ! $task->creatorEditWindowExpired($currentUser->id));
                                $canStartTask = ! $hasSubtasks
                                    && in_array($currentStatus, ['pending', 'todo'], true)
                                    && $canChangeStatus;
                                $canCompleteTask = $canChangeStatus
                                    && ! $hasSubtasks
                                    && ! in_array($task->status, ['completed', 'done'], true);
                            @endphp
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
                                    <div class="flex justify-end">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                    <div class="mt-2">Progress: {{ $task->progress ?? 0 }}%</div>
                                    @if($task->completed_at)
                                        <div>Completed at {{ $task->completed_at->format($globalDateFormat) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right align-top">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('employee.projects.tasks.show', [$project, $task]) }}" class="inline-flex items-center rounded-full border border-teal-200 px-3 py-1 text-xs font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-600">
                                            OpenTask
                                        </a>
                                        @can('update', $task)
                                            @if($canStartTask)
                                                <form method="POST" action="{{ route('employee.projects.tasks.start', [$project, $task]) }}" class="inline-block">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300">
                                                        Inprogress
                                                    </button>
                                                </form>
                                            @endif
                                            @if($canCompleteTask)
                                                <form method="POST" action="{{ route('employee.projects.tasks.update', [$project, $task]) }}" class="inline-block">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300">
                                                        Complete
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
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
