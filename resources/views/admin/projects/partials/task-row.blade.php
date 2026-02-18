<tr id="task-row-{{ $task->id }}" data-task-id="{{ $task->id }}" class="border-t border-slate-100 align-top">
    @php
        $statusFilter = $statusFilter ?? null;
        $taskEditUrl = route('admin.projects.tasks.edit', array_filter([
            'project' => $project,
            'task' => $task,
            'status' => $statusFilter,
        ], fn ($value) => $value !== null && $value !== ''));
    @endphp
    <td class="px-3 py-2">
        <div class="font-semibold text-slate-900">{{ $task->title }}</div>
        <div class="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">
            {{ $taskTypeOptions[$task->task_type] ?? ucfirst($task->task_type ?? 'Task') }}
        </div>
        @if($task->description)
            <div class="mt-1 whitespace-pre-line text-xs text-slate-500">{{ $task->description }}</div>
        @endif
        <div class="mt-1 text-xs text-slate-500">Opened by: {{ $task->creator?->name ?? '--' }}</div>
        @if($task->customer_visible)
            <div class="text-[11px] font-semibold text-emerald-600">Customer visible</div>
        @endif
    </td>
    <td class="px-3 py-2 text-xs text-slate-600">
        Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }}<br>
        Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }}
    </td>
    <td class="px-3 py-2 text-sm text-slate-700">
        @php
            $assigneeNames = $task->assignments->map(fn ($assignment) => $assignment->assigneeName())->filter()->implode(', ');
            if ($assigneeNames === '' && $task->assigned_type && $task->assigned_id) {
                $assigneeNames = ucfirst(str_replace('_', ' ', $task->assigned_type)) . ' #' . $task->assigned_id;
            }
        @endphp
        <div class="max-w-[220px] truncate">{{ $assigneeNames ?: '--' }}</div>
    </td>
    <td class="px-3 py-2">
        @php
            $statusStyles = [
                'pending' => ['bg' => '#e2e8f0', 'text' => '#475569'],
                'in_progress' => ['bg' => '#fef3c7', 'text' => '#b45309'],
                'blocked' => ['bg' => '#fee2e2', 'text' => '#b91c1c'],
                'completed' => ['bg' => '#dcfce7', 'text' => '#15803d'],
            ];
            $currentStatus = $task->status ?? 'pending';
            $statusStyle = $statusStyles[$currentStatus] ?? $statusStyles['pending'];
        @endphp
        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold" style="background-color: {{ $statusStyle['bg'] }}; color: {{ $statusStyle['text'] }};">
            {{ ucfirst(str_replace('_', ' ', $currentStatus)) }}
        </span>
    </td>
    <td class="px-3 py-2 text-xs text-slate-600 align-top">
        @php
            $progress = (int) ($task->progress ?? 0);
            $progress = max(0, min(100, $progress));
        @endphp
        <div class="flex items-center gap-2">
            <div class="h-2 w-full rounded-full bg-slate-200">
                <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div>
            </div>
            <div class="text-xs text-slate-600">{{ $progress }}%</div>
        </div>
        @if($task->completed_at)
            <div class="mt-1 text-[11px] text-slate-500">Completed {{ $task->completed_at->format($globalDateFormat) }}</div>
        @endif
    </td>
    <td class="px-3 py-2 text-right align-top">
        <div class="flex flex-col items-end gap-2 text-xs font-semibold">
            <a href="{{ route('admin.projects.tasks.show', [$project, $task]) }}" class="text-teal-600 hover:text-teal-500">Open Task</a>

            @can('update', $task)
                <a
                    href="{{ $taskEditUrl }}"
                    data-ajax-modal="true"
                    data-modal-title="Edit Task"
                    data-url="{{ $taskEditUrl }}"
                    class="text-slate-600 hover:text-slate-800"
                >
                    Edit
                </a>

                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('admin.projects.tasks.changeStatus', [$project, $task]) }}" data-ajax-form="true">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="task_status_filter" value="{{ $statusFilter }}">
                        <input type="hidden" name="status" value="in_progress">
                        <input type="hidden" name="progress" value="50">
                        <button type="submit" class="rounded-full border border-amber-200 px-3 py-1 text-[11px] text-amber-700 hover:border-amber-300">
                            In Progress
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.projects.tasks.changeStatus', [$project, $task]) }}" data-ajax-form="true">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="task_status_filter" value="{{ $statusFilter }}">
                        <input type="hidden" name="status" value="completed">
                        <input type="hidden" name="progress" value="100">
                        <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1 text-[11px] text-emerald-700 hover:border-emerald-300">
                            Complete
                        </button>
                    </form>
                </div>
            @endcan

            @can('delete', $task)
                <form method="POST" action="{{ route('admin.projects.tasks.destroy', [$project, $task]) }}" data-ajax-form="true" onsubmit="return confirm('Delete this task?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="task_status_filter" value="{{ $statusFilter }}">
                    <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-rose-600 hover:border-rose-300">
                        Delete
                    </button>
                </form>
            @endcan
        </div>
    </td>
</tr>
