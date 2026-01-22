@extends('layouts.client')

@section('title', 'Project Dashboard')
@section('page-title', 'Project Dashboard')

@section('content')
    <div class="space-y-8">
        {{-- Welcome Section --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">
                    {{ $project->name }}
                </div>
                <div class="mt-1 text-sm text-slate-500">Welcome, {{ $user->name }}</div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('client.projects.show', $project) }}" class="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">View Project</a>
                <a href="{{ route('client.projects.chat', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Project Chat</a>
            </div>
        </div>

        <div class="h-px bg-slate-200/80"></div>

        {{-- Project Status Card --}}
        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project Status</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">
                        @if($project->status === 'active')
                            <span class="text-teal-600">Active</span>
                        @elseif($project->status === 'completed')
                            <span class="text-green-600">Completed</span>
                        @elseif($project->status === 'on_hold')
                            <span class="text-amber-600">On Hold</span>
                        @elseif($project->status === 'cancelled')
                            <span class="text-rose-600">Cancelled</span>
                        @else
                            <span class="text-slate-600">{{ ucfirst($project->status) }}</span>
                        @endif
                    </div>
                </div>
                @if($project->description)
                    <div class="flex-1 text-sm text-slate-600">
                        {{ Str::limit($project->description, 200) }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Statistics Grid --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $totalTasks }}</div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Total Tasks</div>
                    </div>
                    <div class="rounded-2xl bg-slate-100 p-2 text-slate-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 11l3 3L22 4"></path>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-slate-100">
                    <div class="h-1 rounded-full bg-slate-500" style="width: {{ $totalTasks > 0 ? min(100, ($completedTasks / $totalTasks) * 100) : 0 }}%"></div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $inProgressTasks }}</div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">In Progress</div>
                    </div>
                    <div class="rounded-2xl bg-blue-100 p-2 text-blue-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 6v6l4 2"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-blue-100">
                    <div class="h-1 w-3/4 rounded-full bg-blue-500"></div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $completedTasks }}</div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Completed</div>
                    </div>
                    <div class="rounded-2xl bg-teal-100 p-2 text-teal-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <path d="M22 4L12 14.01l-3-3"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-teal-100">
                    <div class="h-1 w-full rounded-full bg-teal-500"></div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-semibold text-slate-900">{{ $unreadMessagesCount }}</div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Unread</div>
                    </div>
                    <div class="rounded-2xl bg-amber-100 p-2 text-amber-600">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4 6h16v9H7l-3 3z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-amber-100">
                    <div class="h-1 w-1/2 rounded-full bg-amber-500"></div>
                </div>
            </div>
        </div>

        @if(!empty($showTasksWidget))
            @include('tasks.partials.dashboard-widget', [
                'taskSummary' => $taskSummary,
                'openTasks' => $openTasks,
                'inProgressTasks' => $inProgressTasks,
                'routePrefix' => 'client',
                'usesStartRoute' => false,
            ])
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Recent Tasks --}}
            <div class="card p-6">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Recent Tasks</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900">Latest Updates</div>
                    </div>
                    <a href="{{ route('client.projects.show', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-700">View All →</a>
                </div>

                @if($recentTasks->isEmpty())
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center">
                        <div class="text-sm text-slate-500">No tasks yet</div>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentTasks as $task)
                            <a href="{{ route('client.projects.tasks.show', [$project, $task]) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-teal-300 hover:shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1">
                                        <div class="font-medium text-slate-900">{{ $task->title }}</div>
                                        @if($task->description)
                                            <div class="mt-1 text-xs text-slate-500">{{ Str::limit($task->description, 80) }}</div>
                                        @endif
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @if($task->status === 'completed')
                                                <span class="inline-flex items-center rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700">Completed</span>
                                            @elseif($task->status === 'in_progress')
                                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">In Progress</span>
                                            @elseif($task->status === 'blocked')
                                                <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">Blocked</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">To Do</span>
                                            @endif
                                            @if($task->assignments->isNotEmpty())
                                                <span class="text-xs text-slate-500">
                                                    👤 {{ $task->assignments->map(fn($a) => $a->assigneeName())->implode(', ') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($task->due_date)
                                        <div class="text-right">
                                            <div class="text-xs text-slate-400">Due</div>
                                            <div class="text-xs font-medium text-slate-600">
                                                {{ \Carbon\Carbon::parse($task->due_date)->format('M d') }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Recent Messages & Support --}}
            <div class="space-y-6">
                {{-- Recent Chat Messages --}}
                <div class="card p-6">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project Chat</div>
                            <div class="mt-1 text-lg font-semibold text-slate-900">Recent Messages</div>
                        </div>
                        <a href="{{ route('client.projects.chat', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-700">View Chat →</a>
                    </div>

                    @if($recentMessages->isEmpty())
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center">
                            <div class="text-sm text-slate-500">No messages yet</div>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($recentMessages as $message)
                                <div class="rounded-2xl border border-slate-200 bg-white p-3">
                                    <div class="flex items-start gap-3">
                                        <div class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-full bg-teal-100 text-xs font-semibold text-teal-700">
                                            {{ substr($message->authorName() ?? 'U', 0, 1) }}
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <div class="text-xs font-semibold text-slate-900">{{ $message->authorName() ?? 'User' }}</div>
                                                <div class="text-xs text-slate-400">{{ $message->created_at->diffForHumans() }}</div>
                                            </div>
                                            <div class="mt-1 text-xs text-slate-600">{{ Str::limit($message->message, 100) }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Support Tickets --}}
                <div class="card p-6">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Support</div>
                            <div class="mt-1 text-lg font-semibold text-slate-900">Your Tickets</div>
                        </div>
                        <a href="{{ route('client.support-tickets.index') }}" class="text-xs font-semibold text-teal-600 hover:text-teal-700">View All →</a>
                    </div>

                    <div class="mb-4 flex items-center gap-2">
                        <div class="text-2xl font-semibold text-slate-900">{{ $openTicketsCount }}</div>
                        <div class="text-sm text-slate-500">open {{ Str::plural('ticket', $openTicketsCount) }}</div>
                    </div>

                    @if($recentTickets->isEmpty())
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-center">
                            <div class="text-sm text-slate-500">No tickets</div>
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach($recentTickets as $ticket)
                                <a href="{{ route('client.support-tickets.show', $ticket) }}" class="block rounded-2xl border border-slate-200 bg-white p-3 transition hover:border-teal-300">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex-1">
                                            <div class="text-xs font-semibold text-slate-900">{{ $ticket->subject }}</div>
                                            <div class="mt-1 text-xs text-slate-500">Updated {{ $ticket->updated_at->diffForHumans() }}</div>
                                        </div>
                                        @if($ticket->status === 'open')
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Open</span>
                                        @elseif($ticket->status === 'customer_reply')
                                            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Reply</span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ ucfirst($ticket->status) }}</span>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Task Status Breakdown --}}
        <div class="card p-6">
            <div class="mb-6">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Task Breakdown</div>
                <div class="mt-1 text-lg font-semibold text-slate-900">Progress Overview</div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">To Do</div>
                    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $todoTasks }}</div>
                    <div class="mt-3 h-2 w-full rounded-full bg-slate-200">
                        <div class="h-2 rounded-full bg-slate-500" style="width: {{ $totalTasks > 0 ? ($todoTasks / $totalTasks) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-blue-400">In Progress</div>
                    <div class="mt-2 text-2xl font-semibold text-blue-900">{{ $inProgressTasks }}</div>
                    <div class="mt-3 h-2 w-full rounded-full bg-blue-200">
                        <div class="h-2 rounded-full bg-blue-500" style="width: {{ $totalTasks > 0 ? ($inProgressTasks / $totalTasks) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-teal-400">Completed</div>
                    <div class="mt-2 text-2xl font-semibold text-teal-900">{{ $completedTasks }}</div>
                    <div class="mt-3 h-2 w-full rounded-full bg-teal-200">
                        <div class="h-2 rounded-full bg-teal-500" style="width: {{ $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                    <div class="text-xs uppercase tracking-[0.2em] text-rose-400">Blocked</div>
                    <div class="mt-2 text-2xl font-semibold text-rose-900">{{ $blockedTasks }}</div>
                    <div class="mt-3 h-2 w-full rounded-full bg-rose-200">
                        <div class="h-2 rounded-full bg-rose-500" style="width: {{ $totalTasks > 0 ? ($blockedTasks / $totalTasks) * 100 : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
