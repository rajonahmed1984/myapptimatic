@extends('layouts.client')

@section('title', 'Projects')
@section('page-title', 'Projects')

@section('content')
    <div class="mb-6">
        <div class="section-label">Projects</div>
        <div class="text-2xl font-semibold text-slate-900">Your projects</div>
        <div class="text-sm text-slate-500">Projects associated with your account.</div>
    </div>

    <div class="card p-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white/80">
            <table class="w-full text-left text-sm text-slate-700">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 w-20">ID</th>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Maintenance</th>
                    <th class="px-4 py-3">Billing Type</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($projects as $project)
                    @if($project->maintenances->isNotEmpty())
                        @foreach($project->maintenances as $maintenance)
                            <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                                @if($loop->first)
                                    <td class="px-4 py-3 font-semibold text-slate-900" rowspan="{{ $project->maintenances->count() }}">#{{ $project->id }}</td>
                                    <td class="px-4 py-3" rowspan="{{ $project->maintenances->count() }}">{{ $project->name }}</td>
                                    <td class="px-4 py-3" rowspan="{{ $project->maintenances->count() }}">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst(str_replace('_',' ', $project->status)) }}</span>
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-slate-900">{{ $maintenance->title }}</td>
                                <td class="px-4 py-3">{{ ucfirst($maintenance->billing_cycle) }}</td>
                                <td class="px-4 py-3 font-semibold text-teal-600">{{ $maintenance->currency }}{{ number_format($maintenance->amount, 2) }}</td>
                                @if($loop->first)
                                    <td class="px-4 py-3 text-right" rowspan="{{ $project->maintenances->count() }}">
                                        <a href="{{ route('client.projects.show', $project) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-600">View</a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @else
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-semibold text-slate-900">#{{ $project->id }}</td>
                            <td class="px-4 py-3">{{ $project->name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst(str_replace('_',' ', $project->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-400">--</td>
                            <td class="px-4 py-3 text-slate-400">--</td>
                            <td class="px-4 py-3 text-slate-400">--</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('client.projects.show', $project) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-600">View</a>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">No projects found.</td>
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
