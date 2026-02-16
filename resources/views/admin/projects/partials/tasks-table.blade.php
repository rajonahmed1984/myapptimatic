<div id="tasksTableWrap" class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
    <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks</div>

    @if($tasks->count() > 0)
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
    @else
        <div class="text-xs text-slate-500">No tasks created yet.</div>
    @endif
</div>
