@extends('layouts.admin')

@section('title', 'Leave Requests')
@section('page-title', 'Leave Requests')

@section('content')
    <div class="card p-6">
        <div class="section-label">HR</div>
        <div class="text-2xl font-semibold text-slate-900">Leave requests</div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2">Employee</th>
                    <th class="py-2">Type</th>
                    <th class="py-2">Dates</th>
                    <th class="py-2">Days</th>
                    <th class="py-2">Status</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($leaveRequests as $leave)
                    <tr class="border-b border-slate-100">
                        <td class="py-2">{{ $leave->employee?->name ?? '—' }}</td>
                        <td class="py-2">{{ $leave->leaveType?->name ?? '—' }}</td>
                        <td class="py-2">{{ $leave->start_date?->format($globalDateFormat) }} - {{ $leave->end_date?->format($globalDateFormat) }}</td>
                        <td class="py-2">{{ $leave->total_days }}</td>
                        <td class="py-2">{{ ucfirst($leave->status) }}</td>
                        <td class="py-2 text-right space-x-2">
                            @if($leave->status === 'pending')
                                <form method="POST" action="{{ route('admin.hr.leave-requests.approve', $leave) }}" class="inline">
                                    @csrf
                                    <button class="text-xs text-emerald-700 hover:underline">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.hr.leave-requests.reject', $leave) }}" class="inline">
                                    @csrf
                                    <button class="text-xs text-rose-600 hover:underline">Reject</button>
                                </form>
                            @else
                                <span class="text-xs text-slate-500">Locked</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-3 text-center text-slate-500">No leave requests.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $leaveRequests->links() }}</div>
    </div>
@endsection
