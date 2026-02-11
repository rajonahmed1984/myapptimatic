@extends('layouts.admin')

@section('title', 'Projects')
@section('page-title', 'Projects')

@section('content')
    @php
        $statusStyles = [
            'ongoing' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            'hold' => 'bg-amber-100 text-amber-700 ring-amber-200',
            'complete' => 'bg-blue-100 text-blue-700 ring-blue-200',
            'cancel' => 'bg-rose-100 text-rose-700 ring-rose-200',
        ];
    @endphp

    <div class="card p-6 space-y-4">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b-1">
            <form method="GET" class="grid gap-3 p-2 md:grid-cols-4">
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected($statusFilter === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Type</label>
                    <select name="type" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected($typeFilter === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="self-end">
                    <button type="submit" class="w-full rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Apply filters</button>
                </div>
            </form>
            <a href="{{ route('admin.projects.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">New project</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-300 bg-white/80">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm text-slate-700">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 w-16">ID</th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3 text-right">Tasks</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($projects as $project)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-semibold text-slate-900">#{{ $project->id }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.projects.show', $project) }}" class="font-semibold text-slate-900 hover:text-teal-700">
                                {{ $project->name }}
                            </a>
                            @if($project->employees->isNotEmpty() || $project->salesRepresentatives->isNotEmpty())
                                <div class="mt-1 text-xs text-slate-500">
                                    @if($project->employees->isNotEmpty())
                                        <div class="flex items-center gap-1">
                                            <span class="font-medium">Employees:</span>
                                            <span>{{ $project->employees->pluck('name')->join(', ') }}</span>
                                        </div>
                                    @endif
                                    @if($project->salesRepresentatives->isNotEmpty())
                                        <div class="flex items-center gap-1">
                                            <span class="font-medium">Sales:</span>
                                            <span>{{ $project->salesRepresentatives->pluck('name')->join(', ') }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800">{{ $project->customer?->name ?? '--' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ ucfirst($project->type) }}</td>
                        <td class="px-4 py-3">
                            @php
                                $status = $project->status;
                                $statusClass = $statusStyles[$status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                            @endphp
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClass }}">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            {{ $project->due_date ? $project->due_date->format($globalDateFormat) : '--' }}
                        </td>
                        @php
                            $openTasks = (int) ($project->open_tasks_count ?? 0);
                            $doneTasks = (int) ($project->done_tasks_count ?? 0);
                            $openSubtasks = (int) ($project->open_subtasks_count ?? 0);
                            $hasOpenWork = ($openTasks > 0) || ($openSubtasks > 0);
                        @endphp
                        <td class="px-4 py-3 text-right text-sm {{ $hasOpenWork ? 'bg-amber-50 text-amber-700 font-semibold' : 'text-slate-600' }}">
                            {{ $doneTasks }}/{{ $openTasks + $doneTasks }} done
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.projects.show', $project) }}" class="text-slate-700 hover:text-teal-700 font-semibold">View</a>
                                <a href="{{ route('admin.projects.edit', $project) }}" class="text-slate-700 hover:text-teal-700 font-semibold">Edit</a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.projects.destroy', $project) }}"
                                    data-delete-confirm
                                    data-confirm-name="{{ $project->name }}"
                                    data-confirm-title="Delete project {{ $project->name }}?"
                                    data-confirm-description="This will permanently delete the project and related data."
                                    class="inline"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-700 font-semibold">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-sm text-slate-500">No projects found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-2">
            {{ $projects->links() }}
        </div>
    </div>
@endsection
