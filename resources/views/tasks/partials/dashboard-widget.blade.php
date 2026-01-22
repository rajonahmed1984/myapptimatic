@php
    $taskSummary = $taskSummary ?? ['open' => 0, 'in_progress' => 0, 'completed' => 0];
    $openTasks = $openTasks ?? collect();
    $inProgressTasks = $inProgressTasks ?? collect();
    $routePrefix = $routePrefix ?? 'admin';
    $usesStartRoute = $usesStartRoute ?? false;

    $tasksIndexRoute = $routePrefix . '.tasks.index';
    $projectShowRoute = $routePrefix . '.projects.show';
    $taskShowRoute = $routePrefix . '.projects.tasks.show';
    $taskUpdateRoute = $routePrefix . '.projects.tasks.update';
    $taskStartRoute = $usesStartRoute ? $routePrefix . '.projects.tasks.start' : null;

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
@endphp

<div class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Tasks</div>
            <div class="text-sm text-slate-500">Quick access to your task backlog.</div>
        </div>
        <a href="{{ route($tasksIndexRoute) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View all</a>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-3 text-sm text-slate-600">
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Open</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">{{ $taskSummary['open'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">In Progress</div>
            <div class="mt-1 text-lg font-semibold text-amber-600">{{ $taskSummary['in_progress'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Completed</div>
            <div class="mt-1 text-lg font-semibold text-emerald-600">{{ $taskSummary['completed'] ?? 0 }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">My Open Tasks</div>
            <div class="mt-3 space-y-3 text-sm">
                @forelse($openTasks as $task)
                    @php
                        $currentStatus = $task->status ?? 'pending';
                        $statusLabel = $statusLabels[$currentStatus] ?? ucfirst(str_replace('_', ' ', $currentStatus));
                        $statusClass = $statusClasses[$currentStatus] ?? 'bg-slate-100 text-slate-600';
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $task->title }}</div>
                                @if($task->project)
                                    <a href="{{ route($projectShowRoute, $task->project) }}" class="text-xs text-slate-500 hover:text-teal-600">
                                        {{ $task->project->name ?? 'Project' }}
                                    </a>
                                @endif
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                            @if($task->project)
                                <a href="{{ route($taskShowRoute, [$task->project, $task]) }}" class="text-teal-600 hover:text-teal-500">Open</a>
                            @endif
                            @if($task->can_start && $task->project)
                                <form method="POST" action="{{ $usesStartRoute ? route($taskStartRoute, [$task->project, $task]) : route($taskUpdateRoute, [$task->project, $task]) }}">
                                    @csrf
                                    @method('PATCH')
                                    @unless($usesStartRoute)
                                        <input type="hidden" name="status" value="in_progress">
                                    @endunless
                                    <button type="submit" class="rounded-full border border-amber-200 px-2 py-0.5 text-[11px] font-semibold text-amber-700 hover:border-amber-300">
                                        Inprogress
                                    </button>
                                </form>
                            @endif
                            @if($task->can_complete && $task->project)
                                <form method="POST" action="{{ route($taskUpdateRoute, [$task->project, $task]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                        Complete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No open tasks right now.</div>
                @endforelse
            </div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">My Inprogress Tasks</div>
            <div class="mt-3 space-y-3 text-sm">
                @forelse($inProgressTasks as $task)
                    @php
                        $currentStatus = $task->status ?? 'in_progress';
                        $statusLabel = $statusLabels[$currentStatus] ?? ucfirst(str_replace('_', ' ', $currentStatus));
                        $statusClass = $statusClasses[$currentStatus] ?? 'bg-slate-100 text-slate-600';
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $task->title }}</div>
                                @if($task->project)
                                    <a href="{{ route($projectShowRoute, $task->project) }}" class="text-xs text-slate-500 hover:text-teal-600">
                                        {{ $task->project->name ?? 'Project' }}
                                    </a>
                                @endif
                            </div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                            @if($task->project)
                                <a href="{{ route($taskShowRoute, [$task->project, $task]) }}" class="text-teal-600 hover:text-teal-500">Open</a>
                            @endif
                            @if($task->can_complete && $task->project)
                                <form method="POST" action="{{ route($taskUpdateRoute, [$task->project, $task]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                        Complete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">No tasks in progress.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
