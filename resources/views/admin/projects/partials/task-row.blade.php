<tr id="task-row-{{ $task->id }}" class="border-t border-slate-100 align-top">
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
        {{ $assigneeNames ?: '--' }}
    </td>
    <td class="px-3 py-2">
        @can('update', $task)
            <form method="POST" action="{{ route('admin.projects.tasks.update', [$project, $task]) }}" class="space-y-2 task-update-form" data-task-id="{{ $task->id }}">
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
    <td class="px-3 py-2 text-right align-top">
        <a href="{{ route('admin.projects.tasks.show', [$project, $task]) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Open Task</a>
    </td>
</tr>
