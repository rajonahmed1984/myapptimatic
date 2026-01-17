@extends('layouts.rep')

@section('title', 'Projects')

@section('content')
    <div class="mb-6">
        <div class="section-label">My projects</div>
        <div class="text-2xl font-semibold text-slate-900">Assigned projects</div>
        <div class="text-sm text-slate-500">Projects you are assigned to as a sales representative.</div>
    </div>

    <div class="card p-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white/80">
            <table class="w-full text-left text-sm text-slate-700">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 w-20">ID</th>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Commission</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($projects as $project)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-semibold text-slate-900">#{{ $project->id }}</td>
                        <td class="px-4 py-3">{{ $project->name }}</td>
                        <td class="px-4 py-3">{{ $project->customer?->name ?? '--' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst(str_replace('_',' ', $project->status)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">
                            @php
                                $commission = $commissionMap[$project->id] ?? null;
                            @endphp
                            {{ $commission !== null ? number_format((float) $commission, 2) . ' ' . $project->currency : '--' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('rep.projects.show', $project) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-600">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No projects assigned.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $projects->links() }}
        </div>
    </div>
@endsection
