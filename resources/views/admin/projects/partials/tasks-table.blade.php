@php
    $statusFilter = $statusFilter ?? null;
    $statusFilterLabel = match ($statusFilter) {
        'in_progress' => 'Inprogress',
        'completed' => 'Completed',
        default => $statusFilter ? ucfirst(str_replace('_', ' ', $statusFilter)) : 'All',
    };
    $statusLabels = [
        'pending' => 'Open',
        'todo' => 'Open',
        'in_progress' => 'Inprogress',
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
@endphp

<div id="tasksTableWrap" class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Tasks</div>
            <div class="text-sm text-slate-500">Tasks for this project. Filter: {{ $statusFilterLabel }}</div>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3">Project Task</th>
                    <th class="px-4 py-3">Created By</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($tasks as $task)
                    @php
                        $currentStatus = $task->status ?? 'pending';
                        $statusLabel = $statusLabels[$currentStatus] ?? ucfirst(str_replace('_', ' ', $currentStatus));
                        $statusClass = $statusClasses[$currentStatus] ?? 'bg-slate-100 text-slate-600';
                        $assigneeNames = $task->assignments->map(fn ($assignment) => $assignment->assigneeName())->filter()->implode(', ');
                        if ($assigneeNames === '' && $task->assigned_type && $task->assigned_id) {
                            $assigneeNames = ucfirst(str_replace('_', ' ', $task->assigned_type)) . ' #' . $task->assigned_id;
                        }
                        $progress = (int) ($task->progress ?? 0);
                        $progress = max(0, min(100, $progress));
                        $taskEditUrl = route('admin.projects.tasks.edit', array_filter([
                            'project' => $project,
                            'task' => $task,
                            'status' => $statusFilter,
                        ], fn ($value) => $value !== null && $value !== ''));
                    @endphp
                    <tr class="align-top">
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                            <div class="whitespace-nowrap">{{ $task->created_at?->format($globalDateFormat) ?? '--' }}</div>
                            <div class="text-xs text-slate-400 whitespace-nowrap">{{ $task->created_at?->format('H:i') ?? '--' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">
                                <a href="{{ route('admin.projects.tasks.show', [$project, $task]) }}" class="text-teal-600 hover:text-teal-500">
                                    {{ $task->title }}
                                </a>
                            </div>
                            @if($task->description)
                                <div class="mt-1 text-xs text-slate-500 whitespace-pre-line">{{ $task->description }}</div>
                            @endif
                            <div class="mt-1 text-xs text-slate-500">
                                {{ $taskTypeOptions[$task->task_type] ?? ucfirst($task->task_type ?? 'Task') }}
                                | Assignee: {{ $assigneeNames ?: '--' }}
                                | Progress: {{ $progress }}%
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $task->creator?->name ?? 'System' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-col items-end gap-2 text-xs font-semibold">
                                <a href="{{ route('admin.projects.tasks.show', [$project, $task]) }}" class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300">Open Task</a>

                                @can('update', $task)
                                    @php
                                        $isInProgress = $currentStatus === 'in_progress';
                                        $isCompleted = in_array($currentStatus, ['completed', 'done'], true);
                                    @endphp
                                    <a
                                        href="{{ $taskEditUrl }}"
                                        data-ajax-modal="true"
                                        data-modal-title="Edit Task"
                                        data-url="{{ $taskEditUrl }}"
                                        class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-slate-300"
                                    >
                                        Edit
                                    </a>

                                    @if($statusFilter !== 'in_progress' && ! $isInProgress && ! $isCompleted)
                                        <form method="POST" action="{{ route('admin.projects.tasks.changeStatus', [$project, $task]) }}" data-ajax-form="true">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="task_status_filter" value="{{ $statusFilter }}">
                                            <input type="hidden" name="status" value="in_progress">
                                            <input type="hidden" name="progress" value="50">
                                            <button type="submit" class="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300">
                                                Inprogress
                                            </button>
                                        </form>
                                    @endif

                                    @if($statusFilter !== 'completed' && ! $isCompleted)
                                        <form method="POST" action="{{ route('admin.projects.tasks.changeStatus', [$project, $task]) }}" data-ajax-form="true">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="task_status_filter" value="{{ $statusFilter }}">
                                            <input type="hidden" name="status" value="completed">
                                            <input type="hidden" name="progress" value="100">
                                            <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300">
                                                Complete
                                            </button>
                                        </form>
                                    @endif
                                @endcan

                                @can('delete', $task)
                                    <form method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $task]) }}" data-ajax-form="true" onsubmit="return confirm('Delete this task?');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="task_status_filter" value="{{ $statusFilter }}">
                                        <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700 hover:border-rose-300">
                                            Delete
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">
                            {{ $statusFilter ? 'No tasks found for this status.' : 'No tasks found.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tasks->hasPages())
        <div class="mt-4">
            {{ $tasks->links() }}
        </div>
    @endif
</div>
