@extends('layouts.client')

@section('title', 'Project Dashboard')
@section('page-title', 'Project Dashboard')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
                <div class="mt-1 text-sm text-slate-500">Welcome, {{ $user->name }}</div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('client.projects.show', $project) }}" class="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">Project Details</a>
                <a href="{{ route('client.projects.chat', $project) }}" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Open Chat</a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total Tasks</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $totalTasks }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">In Progress</div>
                <div class="mt-2 text-2xl font-semibold text-blue-700">{{ $inProgressTaskCount }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Completed</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ $completedTasks }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Unread Chat</div>
                <div class="mt-2 text-2xl font-semibold text-amber-700">{{ $unreadMessagesCount }}</div>
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

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <div class="section-label">Recent Tasks</div>
                    <a href="{{ route('client.projects.show', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View all</a>
                </div>
                <div class="mt-4 space-y-2 text-sm">
                    @forelse($recentTasks as $task)
                        <a href="{{ route('client.projects.tasks.show', [$project, $task]) }}" class="block rounded-xl border border-slate-200 bg-white px-3 py-2 hover:border-teal-300">
                            <div class="font-semibold text-slate-900">{{ $task->title }}</div>
                            <div class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', (string) $task->status)) }}</div>
                        </a>
                    @empty
                        <div class="text-sm text-slate-500">No tasks yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <div class="section-label">Recent Chat Messages</div>
                    <a href="{{ route('client.projects.chat', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Open chat</a>
                </div>
                <div class="mt-4 space-y-2 text-sm">
                    @forelse($recentMessages as $message)
                        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                            <div class="text-xs font-semibold text-slate-700">{{ $message->authorName() }}</div>
                            <div class="text-sm text-slate-900">{{ \Illuminate\Support\Str::limit((string) ($message->message ?? 'Attachment'), 120) }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No chat messages yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
