@extends('layouts.rep')

@section('title', 'My Earnings')
@section('page-title', 'My Earnings')

@section('content')
    <div class="card p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="section-label">Commissions</div>
                <h1 class="text-2xl font-semibold text-slate-900">Earnings</h1>
                <div class="text-sm text-slate-500">Read-only view of your commission earnings.</div>
            </div>
            <a href="{{ route('rep.dashboard') }}" class="text-sm text-slate-600 hover:text-slate-800">Dashboard</a>
        </div>

        <form method="GET" class="grid gap-3 md:grid-cols-4">
            <div>
                <label class="text-xs text-slate-500">Status</label>
                <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead>
                        <tr class="text-xs uppercase text-slate-500">
                            <th class="px-2 py-2">ID</th>
                            <th class="px-2 py-2">Source</th>
                            <th class="px-2 py-2">Customer</th>
                            <th class="px-2 py-2">Paid amount</th>
                            <th class="px-2 py-2">Commission</th>
                            <th class="px-2 py-2">Status</th>
                            <th class="px-2 py-2">Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($earnings as $earning)
                            <tr class="border-t border-slate-200">
                                <td class="px-2 py-2">#{{ $earning->id }}</td>
                                <td class="px-2 py-2">
                                    {{ ucfirst($earning->source_type) }}
                                    @if($earning->invoice)
                                        (Invoice #{{ $earning->invoice->id }})
                                    @elseif($earning->project)
                                        (Project #{{ $earning->project->id }})
                                    @endif
                                </td>
                                <td class="px-2 py-2">{{ $earning->customer?->name ?? '—' }}</td>
                                <td class="px-2 py-2">{{ number_format($earning->paid_amount, 2) }} {{ $earning->currency }}</td>
                                <td class="px-2 py-2">{{ number_format($earning->commission_amount, 2) }} {{ $earning->currency }}</td>
                                <td class="px-2 py-2">{{ ucfirst($earning->status) }}</td>
                                <td class="px-2 py-2">{{ $earning->earned_at?->format($globalDateFormat.' H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-2 py-3 text-slate-500">No earnings found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $earnings->links() }}
            </div>
        </div>
    </div>
@endsection
