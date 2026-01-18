@extends('layouts.admin')

@section('title', 'Projects')
@section('page-title', 'Projects')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">Projects</div>
            <div class="text-sm text-slate-500">Track software/website projects and their tasks.</div>
        </div>
        <a href="{{ route('admin.projects.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">New project</a>
    </div>

    <div class="card p-6 space-y-4">
        <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white/70 p-4 md:grid-cols-4">
            <div>
                <label class="text-xs text-slate-500">Status</label>
                <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected($statusFilter === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Type</label>
                <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}" @selected($typeFilter === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="self-end">
                <button type="submit" class="w-full rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Apply filters</button>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white/80">
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
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            {{ $project->due_date ? $project->due_date->format($globalDateFormat) : '--' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-slate-600">
                            {{ $project->done_tasks_count ?? 0 }}/{{ ($project->open_tasks_count ?? 0) + ($project->done_tasks_count ?? 0) }} done
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.projects.show', $project) }}" class="text-slate-700 hover:text-teal-700 font-semibold">View</a>
                                <a href="{{ route('admin.projects.edit', $project) }}" class="text-slate-700 hover:text-teal-700 font-semibold">Edit</a>
                                <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?');" class="inline">
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
