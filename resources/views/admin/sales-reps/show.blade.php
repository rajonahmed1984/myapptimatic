@extends('layouts.admin')

@section('title', $rep->name)
@section('page-title', $rep->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Sales Representative</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $rep->name }}</div>
            <div class="text-sm text-slate-500">{{ $rep->email ?? 'No email on file' }}</div>
        </div>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('admin.sales-reps.impersonate', $rep) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">
                    Login as Sales Representative
                </button>
            </form>
            <a href="{{ route('admin.sales-reps.edit', $rep) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <a href="{{ route('admin.sales-reps.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to list</a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Total Earned</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['total_earned'] ?? 0, 2) }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Payable</div>
            <div class="mt-2 text-2xl font-semibold text-amber-700">{{ number_format($summary['payable'] ?? 0, 2) }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Paid</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['paid'] ?? 0, 2) }}</div>
        </div>
    </div>

    <div class="mt-8">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="card p-4 md:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Profile</div>
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm text-slate-700">
                    <div>
                        <dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Status</dt>
                        <dd class="mt-1">
                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $rep->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-200 text-slate-600 bg-slate-50' }}">
                                {{ ucfirst($rep->status) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.2em] text-slate-500">User</dt>
                        <dd class="mt-1 text-slate-800">{{ $rep->user?->name ?? '--' }} <span class="text-slate-500">{{ $rep->user?->email }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Employee</dt>
                        <dd class="mt-1 text-slate-800">{{ $rep->employee?->name ?? 'Not linked' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.2em] text-slate-500">Phone</dt>
                        <dd class="mt-1 text-slate-800">{{ $rep->phone ?? '--' }}</dd>
                    </div>
                </dl>
            </div>
            <div class="card p-4 md:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Products / Services</div>
                </div>
                <div class="text-sm text-slate-600">No linked products or services for this rep.</div>
            </div>
            <div class="card p-4 md:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Invoices</div>
                </div>
                <div class="text-sm text-slate-600">No invoices linked to this rep.</div>
            </div>
            <div class="card p-4 md:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Tickets</div>
                </div>
                <div class="text-sm text-slate-600">No support tickets linked to this rep.</div>
            </div>
            <div class="card p-4 md:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Emails</div>
                </div>
                <div class="text-sm text-slate-600">No email history available.</div>
            </div>
            <div class="card p-4 md:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Log</div>
                </div>
                <div class="text-sm text-slate-600">No activity log entries.</div>
            </div>
        </div>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-2">
        <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Recent Earnings</div>
                <a href="{{ route('admin.commission-payouts.create', ['sales_rep_id' => $rep->id]) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">
                    Pay payable ({{ number_format($summary['payable'] ?? 0, 2) }})
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[500px] text-sm text-slate-700">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Status</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEarnings as $earning)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $earning->created_at?->format($globalDateFormat ?? 'Y-m-d') }}</td>
                                <td class="py-2">{{ ucfirst($earning->status) }}</td>
                                <td class="py-2 text-right">{{ number_format($earning->commission_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-slate-500">No earnings yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card p-4">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Recent Payouts</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[500px] text-sm text-slate-700">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="py-2 text-left">Date</th>
                            <th class="py-2 text-left">Method</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayouts as $payout)
                            <tr class="border-b border-slate-100">
                                <td class="py-2">{{ $payout->created_at?->format($globalDateFormat ?? 'Y-m-d') }}</td>
                                <td class="py-2">{{ ucfirst($payout->method ?? 'manual') }}</td>
                                <td class="py-2 text-right">{{ number_format($payout->amount ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-slate-500">No payouts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
