@extends('layouts.admin')

@section('title', 'Timesheets')
@section('page-title', 'Timesheets')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Timesheets</div>
        </div>
    </div>

    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2 px-3">Employee</th>
                    <th class="py-2 px-3">Period</th>
                    <th class="py-2 px-3">Hours</th>
                    <th class="py-2 px-3">Status</th>
                    <th class="py-2 px-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($timesheets as $sheet)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 px-3">{{ $sheet->employee?->name ?? '--' }}</td>
                        <td class="py-2 px-3">{{ $sheet->period_start?->format($globalDateFormat) }} - {{ $sheet->period_end?->format($globalDateFormat) }}</td>
                        <td class="py-2 px-3">{{ $sheet->total_hours }}</td>
                        <td class="py-2 px-3">{{ ucfirst($sheet->status) }}</td>
                        <td class="py-2 px-3 text-right space-x-2">
                            @if(in_array($sheet->status, ['submitted','draft']))
                                <form method="POST" action="{{ route('admin.hr.timesheets.approve', $sheet) }}" class="inline">
                                    @csrf
                                    <button class="text-xs text-emerald-700 hover:underline">Approve</button>
                                </form>
                            @endif
                            @if($sheet->status !== 'locked')
                                <form method="POST" action="{{ route('admin.hr.timesheets.lock', $sheet) }}" class="inline">
                                    @csrf
                                    <button class="text-xs text-slate-700 hover:underline">Lock</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 px-3 text-center text-slate-500">No timesheets.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $timesheets->links() }}</div>
    </div>
@endsection
