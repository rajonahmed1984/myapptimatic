@extends('layouts.admin')

@section('title', 'Leave Requests')
@section('page-title', 'Leave Requests')

@section('content')
    <div class="card p-6">
        <div class="section-label">Employee</div>
        <div class="text-2xl font-semibold text-slate-900">Request leave</div>
        <div class="text-sm text-slate-500">Submit a new request and track approvals.</div>

        <form method="POST" action="{{ route('employee.leave-requests.store') }}" class="mt-4 grid gap-3 md:grid-cols-4 text-sm">
            @csrf
            <div>
                <label class="text-xs text-slate-500">Leave type</label>
                <select name="leave_type_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @foreach($leaveTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Start date</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">End date</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Reason</label>
                <input name="reason" value="{{ old('reason') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional">
            </div>
            <div class="md:col-span-4">
                <button class="rounded-full bg-emerald-600 px-4 py-2 text-white text-sm font-semibold hover:bg-emerald-500">Submit</button>
            </div>
        </form>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2">Type</th>
                    <th class="py-2">Dates</th>
                    <th class="py-2">Days</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Approved at</th>
                </tr>
                </thead>
                <tbody>
                @forelse($leaveRequests as $leave)
                    <tr class="border-b border-slate-100">
                        <td class="py-2">{{ $leave->leaveType?->name ?? '—' }}</td>
                        <td class="py-2">{{ $leave->start_date?->format($globalDateFormat) }} - {{ $leave->end_date?->format($globalDateFormat) }}</td>
                        <td class="py-2">{{ $leave->total_days }}</td>
                        <td class="py-2">{{ ucfirst($leave->status) }}</td>
                        <td class="py-2">{{ $leave->approved_at?->format($globalDateFormat.' H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 text-center text-slate-500">No leave requests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $leaveRequests->links() }}</div>
    </div>
@endsection
