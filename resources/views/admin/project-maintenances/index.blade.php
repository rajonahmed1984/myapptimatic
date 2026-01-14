@extends('layouts.admin')

@section('title', 'Project Maintenance')
@section('page-title', 'Project Maintenance')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Projects</div>
            <div class="text-2xl font-semibold text-slate-900">Maintenance</div>
            <div class="text-sm text-slate-500">Manage recurring maintenance plans for projects.</div>
        </div>
        <a href="{{ route('admin.project-maintenances.create') }}" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add maintenance</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Project</th>
                        <th class="px-4 py-3 text-left">Customer</th>
                        <th class="px-4 py-3 text-left">Title</th>
                        <th class="px-4 py-3 text-left">Cycle</th>
                        <th class="px-4 py-3 text-left">Next Billing</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($maintenances as $maintenance)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 font-semibold text-slate-900">#{{ $maintenance->id }}</td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">
                                    {{ $maintenance->project?->name ?? '--' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $maintenance->customer?->name ?? '--' }}</td>
                            <td class="px-4 py-3">{{ $maintenance->title }}</td>
                            <td class="px-4 py-3">{{ ucfirst($maintenance->billing_cycle) }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $maintenance->next_billing_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $maintenance->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($maintenance->status === 'paused' ? 'border-amber-200 text-amber-700 bg-amber-50' : 'border-slate-200 text-slate-600 bg-slate-50') }}">
                                    {{ ucfirst($maintenance->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">
                                {{ $maintenance->currency }} {{ number_format((float) $maintenance->amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2 text-xs font-semibold">
                                    <a href="{{ route('admin.project-maintenances.edit', $maintenance) }}" class="text-teal-700 hover:text-teal-600">Edit</a>
                                    @if($maintenance->status === 'active')
                                        <form method="POST" action="{{ route('admin.project-maintenances.update', $maintenance) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="quick_status" value="1">
                                            <input type="hidden" name="status" value="paused">
                                            <button type="submit" class="text-amber-700 hover:text-amber-600">Pause</button>
                                        </form>
                                    @elseif($maintenance->status === 'paused')
                                        <form method="POST" action="{{ route('admin.project-maintenances.update', $maintenance) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="quick_status" value="1">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="text-emerald-700 hover:text-emerald-600">Resume</button>
                                        </form>
                                    @endif
                                    @if($maintenance->status !== 'cancelled')
                                        <form method="POST" action="{{ route('admin.project-maintenances.update', $maintenance) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="quick_status" value="1">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="text-rose-600 hover:text-rose-500">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-slate-500">No maintenance plans yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $maintenances->links() }}
    </div>
@endsection
