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
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm text-slate-700">
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
                    @php
                        $projectAmount = $project->total_budget ?? $project->budget_amount;
                        $projectAmountLabel = $projectAmount !== null
                            ? ($project->currency.' '.number_format($projectAmount, 2))
                            : '--';
                    @endphp
                    <tr class="border-t border-slate-100 bg-slate-50/60">
                        <td class="px-4 py-3 font-semibold text-slate-900">#{{ $project->id }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('client.projects.show', $project) }}" class="font-medium text-teal-600 hover:text-teal-500">{{ $project->name }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst(str_replace('_',' ', $project->status)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-400">--</td>
                        <td class="px-4 py-3 text-slate-400">--</td>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $projectAmountLabel }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('client.projects.show', $project) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-600">View</a>
                        </td>
                    </tr>
                    @foreach($project->maintenances as $maintenance)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3 text-slate-400">--</td>
                            <td class="px-4 py-3 text-slate-500">
                                Maintenance
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $maintenance->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($maintenance->status === 'paused' ? 'border-amber-200 text-amber-700 bg-amber-50' : 'border-slate-200 text-slate-600 bg-slate-50') }}">
                                    {{ ucfirst($maintenance->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-900">{{ $maintenance->title }}</td>
                            <td class="px-4 py-3">{{ ucfirst($maintenance->billing_cycle) }}</td>
                            <td class="px-4 py-3 font-semibold text-teal-600">{{ $maintenance->currency }} {{ number_format($maintenance->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-slate-400">--</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">No projects found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $projects->links() }}
        </div>
    </div>
@endsection
