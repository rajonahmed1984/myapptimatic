@extends('layouts.admin')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.projects.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300">Delete</button>
            </form>
        </div>
    </div>

    <div class="card p-6">
        <div class="mt-6 grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                <div class="mt-2 font-semibold text-slate-900">{{ $project->customer?->name ?? '--' }}</div>
                <div class="text-xs text-slate-500">Project ID: {{ $project->id }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Type</div>
                <div class="mt-2 font-semibold text-slate-900">{{ ucfirst($project->type) }}</div>
                <div class="text-xs text-slate-500">Due: {{ $project->due_date?->format($globalDateFormat) ?? '--' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Tasks</div>
                <div class="mt-2 text-sm text-slate-700">{{ $project->done_tasks_count ?? 0 }}/{{ ($project->open_tasks_count ?? 0) + ($project->done_tasks_count ?? 0) }} done</div>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Description</div>
            <div class="mt-2">{{ $project->description ?? 'No description provided.' }}</div>
        </div>

        @if($project->notes)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Notes</div>
                <div class="mt-2">{{ $project->notes }}</div>
            </div>
        @endif

        @if($project->tasks && $project->tasks->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Assignee</th>
                            <th class="px-3 py-2">Due</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($project->tasks as $task)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ $task->title }}</td>
                                <td class="px-3 py-2">{{ ucfirst($task->status) }}</td>
                                <td class="px-3 py-2">{{ $task->assignee?->name ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $task->due_date?->format($globalDateFormat) ?? '--' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
