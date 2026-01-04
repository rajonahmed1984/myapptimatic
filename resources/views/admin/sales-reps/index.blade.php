@extends('layouts.admin')

@section('title', 'Sales Representatives')
@section('page-title', 'Sales Representatives')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="section-label">Sales</div>
                <div class="text-2xl font-semibold text-slate-900">Sales Representatives</div>
                <div class="text-sm text-slate-500">Manage rep accounts and review totals.</div>
            </div>
            <a href="{{ route('admin.sales-reps.create') }}" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add sales rep</a>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Total earned</th>
                        <th class="px-4 py-3 text-right">Payable</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($reps as $rep)
                        @php($repTotals = $totals[$rep->id] ?? null)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $rep->name }}</div>
                                <div class="text-xs text-slate-500">{{ $rep->email ?? '—' }}</div>
                                @if($rep->employee)
                                    <div class="text-xs text-emerald-600">Employee: {{ $rep->employee->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ $rep->user?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $rep->user?->email ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $rep->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-200 text-slate-600 bg-slate-50' }}">
                                    {{ ucfirst($rep->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($repTotals->total_earned ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($repTotals->total_payable ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($repTotals->total_paid ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.sales-reps.edit', $rep) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-600">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">No sales representatives yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
