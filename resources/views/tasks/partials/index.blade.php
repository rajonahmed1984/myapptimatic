@php
    $statusFilter = $statusFilter ?? '';
    $search = $search ?? '';
    $statusCounts = $statusCounts ?? ['open' => 0, 'in_progress' => 0, 'completed' => 0, 'total' => 0];
    $routePrefix = $routePrefix ?? 'admin';
    $usesStartRoute = $usesStartRoute ?? false;
    $showCreator = $showCreator ?? ($routePrefix === 'admin');

    $tasksIndexRoute = $routePrefix . '.tasks.index';
    $projectsIndexRoute = $routePrefix . '.projects.index';
    $projectShowRoute = $routePrefix . '.projects.show';
    $taskShowRoute = $routePrefix . '.projects.tasks.show';
    $taskUpdateRoute = $routePrefix . '.projects.tasks.update';
    $taskStartRoute = $usesStartRoute ? $routePrefix . '.projects.tasks.start' : null;

    $filters = [
        '' => ['label' => 'All', 'count' => $statusCounts['total'] ?? 0],
        'open' => ['label' => 'Open', 'count' => $statusCounts['open'] ?? 0],
        'in_progress' => ['label' => 'Inprogress', 'count' => $statusCounts['in_progress'] ?? 0],
        'completed' => ['label' => 'Completed', 'count' => $statusCounts['completed'] ?? 0],
    ];

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

<div id="tasksIndex" class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Tasks</div>
            <div class="text-sm text-slate-500">All tasks you are allowed to see.</div>
        </div>
        <div class="flex items-center gap-3 text-xs font-semibold">
            <a href="{{ route($projectsIndexRoute) }}" class="text-slate-500 hover:text-teal-600">
                Projects
            </a>
            <a href="{{ route($tasksIndexRoute) }}" class="text-teal-600 hover:text-teal-500">
                Reset filters
            </a>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2 text-xs">
            @foreach($filters as $key => $filter)
                @php
                    $isActive = $statusFilter === $key || ($key === '' && $statusFilter === '');
                    $query = array_filter(['status' => $key, 'search' => $search], fn ($value) => $value !== null && $value !== '');
                @endphp
                <a href="{{ route($tasksIndexRoute, $query) }}"
                   hx-get="{{ route($tasksIndexRoute, $query) }}"
                   hx-target="#tasksIndex"
                   hx-swap="outerHTML"
                   hx-push-url="true"
                   class="inline-flex items-center gap-2 rounded-full border px-3 py-1 font-semibold {{ $isActive ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600 hover:border-teal-200 hover:text-teal-600' }}">
                    <span>{{ $filter['label'] }}</span>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{{ $filter['count'] }}</span>
                </a>
            @endforeach
        </div>
        <form id="tasksSearchForm" method="GET" action="{{ route($tasksIndexRoute) }}" class="flex items-center gap-2"
              hx-get="{{ route($tasksIndexRoute) }}"
              hx-target="#tasksIndex"
              hx-swap="outerHTML"
              hx-push-url="true">
            @if($statusFilter !== '')
                <input type="hidden" name="status" value="{{ $statusFilter }}">
            @endif
            <input type="text" name="search" value="{{ $search }}" placeholder="Search tasks"
                   class="w-48 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600 focus:border-teal-300 focus:outline-none"
                   hx-get="{{ route($tasksIndexRoute) }}"
                   hx-trigger="keyup changed delay:300ms"
                   hx-target="#tasksIndex"
                   hx-swap="outerHTML"
                   hx-push-url="true"
                   hx-include="#tasksSearchForm">
            <button type="submit" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                Search
            </button>
        </form>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3">Project Task</th>
                    @if($showCreator)
                        <th class="px-4 py-3">Created By</th>
                    @endif
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
                    @endphp
                    <tr class="align-top">
                        <td class="px-4 py-3 text-slate-500">
                            {{ $task->created_at?->format($globalDateFormat) ?? '--' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">
                                <a href="{{ route($taskShowRoute, [$task->project, $task]) }}" class="text-teal-600 hover:text-teal-500">
                                    {{ $task->title }}
                                </a>
                            </div>
                            @if($task->description)
                                <div class="mt-1 text-xs text-slate-500">{{ $task->description }}</div>
                            @endif
                            @if($task->project)
                                <a href="{{ route($projectShowRoute, $task->project) }}" class="hover:text-teal-600">
                                    {{ $task->project->name ?? 'Project' }}
                                </a>
                            @else
                                --
                            @endif
                        </td>
                        @if($showCreator)
                            <td class="px-4 py-3 text-slate-600">
                                {{ $task->createdBy?->name ?? 'System' }}
                            </td>
                        @endif
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-col items-end gap-2 text-xs font-semibold">
                                @if($task->project)
                                    <a href="{{ route($taskShowRoute, [$task->project, $task]) }}" class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300">Open Task</a>
                                @endif
                                @php
                                    $isInProgress = $currentStatus === 'in_progress';
                                    $isCompleted = in_array($currentStatus, ['completed', 'done'], true);
                                @endphp
                                @if($task->can_start && $task->project && $statusFilter !== 'in_progress' && ! $isInProgress && ! $isCompleted)
                                    <form method="POST" action="{{ $usesStartRoute ? route($taskStartRoute, [$task->project, $task]) : route($taskUpdateRoute, [$task->project, $task]) }}" hx-boost="false">
                                        @csrf
                                        @method('PATCH')
                                        @unless($usesStartRoute)
                                            <input type="hidden" name="status" value="in_progress">
                                        @endunless
                                        <button type="submit" class="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300">
                                            Inprogress
                                        </button>
                                    </form>
                                @endif
                                @if($task->can_complete && $task->project && $statusFilter !== 'completed' && ! $isCompleted)
                                    <form method="POST" action="{{ route($taskUpdateRoute, [$task->project, $task]) }}" hx-boost="false">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300">
                                            Complete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showCreator ? 6 : 5 }}" class="px-4 py-6 text-center text-slate-500">No tasks found.</td>
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
