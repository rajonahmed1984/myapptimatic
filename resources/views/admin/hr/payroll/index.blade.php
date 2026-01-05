@extends('layouts.admin')

@section('title', 'Payroll')
@section('page-title', 'Payroll')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Payroll periods</div>
        </div>
        <form method="POST" action="{{ route('admin.hr.payroll.generate') }}" class="flex items-center gap-2">
            @csrf
            <input name="period_key" value="{{ now()->format('Y-m') }}" class="w-28 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            <button class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Generate</button>
        </form>
    </div>

    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2 px-3">Period</th>
                    <th class="py-2 px-3">Dates</th>
                    <th class="py-2 px-3">Status</th>
                    <th class="py-2 px-3">Items</th>
                    <th class="py-2 px-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($periods as $period)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 px-3">{{ $period->period_key }}</td>
                        <td class="py-2 px-3">{{ $period->start_date?->format($globalDateFormat) }} - {{ $period->end_date?->format($globalDateFormat) }}</td>
                        <td class="py-2 px-3">{{ ucfirst($period->status) }}</td>
                        <td class="py-2 px-3">{{ $period->items_count }}</td>
                        <td class="py-2 px-3 text-right space-x-2">
                            <a href="{{ route('admin.hr.payroll.export', $period) }}" class="text-xs text-slate-700 hover:underline">Export CSV</a>
                            @if($period->status === 'draft')
                                <form method="POST" action="{{ route('admin.hr.payroll.finalize', $period) }}" class="inline">
                                    @csrf
                                    <button class="text-xs text-emerald-700 hover:underline">Finalize</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 px-3 text-center text-slate-500">No payroll periods.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $periods->links() }}
        </div>
    </div>
@endsection
