@extends('layouts.admin')

@section('title', 'Project #'.$project->id.' Tasks')
@section('page-title', 'Project Tasks')

@section('content')
    @php
        $taskStatusFilter = $statusFilter ?? null;
        $taskCreateUrl = route('admin.projects.tasks.create', array_filter([
            'project' => $project,
            'status' => $taskStatusFilter,
        ], fn ($value) => $value !== null && $value !== ''));
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.projects.show', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
            <a href="{{ route('admin.projects.chat', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">
                Chat
                @php $projectChatUnreadCount = (int) ($projectChatUnreadCount ?? 0); @endphp
                <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $projectChatUnreadCount > 0 ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                    {{ $projectChatUnreadCount }}
                </span>
            </a>
            <a href="{{ route('admin.projects.edit', $project) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
        </div>
    </div>

    <div class="space-y-4">
        @can('createTask', $project)
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                        <div class="text-xs text-slate-500">Create tasks with modal form, no page reload.</div>
                    </div>
                    <a
                        href="{{ $taskCreateUrl }}"
                        data-ajax-modal="true"
                        data-modal-title="Add Task"
                        data-url="{{ $taskCreateUrl }}"
                        class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                    >
                        + Add Task
                    </a>
                </div>
            </div>
        @endcan

        @include('admin.projects.partials.tasks-stats', [
            'project' => $project,
            'summary' => $summary,
            'statusFilter' => $taskStatusFilter,
        ])

        @include('admin.projects.partials.tasks-table', [
            'project' => $project,
            'tasks' => $tasks,
            'taskTypeOptions' => $taskTypeOptions,
            'statusFilter' => $taskStatusFilter,
        ])
    </div>
@endsection
