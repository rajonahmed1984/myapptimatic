@extends('layouts.admin')

@section('title', 'New Commission Payout')
@section('page-title', 'New Commission Payout')

@section('content')
    <div class="card p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="section-label">Commissions</div>
                <h1 class="text-2xl font-semibold text-slate-900">Create payout</h1>
                <div class="text-sm text-slate-500">Select a sales rep and payable earnings to include.</div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.commission-payouts.create') }}" class="grid gap-3 md:grid-cols-3">
            <div>
                <label class="text-xs text-slate-500">Sales rep</label>
                <select name="sales_rep_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach($salesReps as $rep)
                        <option value="{{ $rep->id }}" @selected($selectedRep == $rep->id)>
                            {{ $rep->name }} @if($rep->status !== 'active') ({{ ucfirst($rep->status) }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.commission-payouts.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="sales_rep_id" value="{{ $selectedRep }}">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Payable earnings</div>
                    <div class="text-xs text-slate-500">Select at least one earning.</div>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr class="text-xs uppercase text-slate-500">
                                <th class="px-2 py-2"><input type="checkbox" class="rounded border-slate-300" onclick="document.querySelectorAll('.earning-checkbox').forEach(cb => cb.checked = this.checked)" /></th>
                                <th class="px-2 py-2">ID</th>
                                <th class="px-2 py-2">Source</th>
                                <th class="px-2 py-2">Customer</th>
                                <th class="px-2 py-2">Commission</th>
                                <th class="px-2 py-2">Earned at</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($earnings as $earning)
                                <tr class="border-t border-slate-200">
                                    <td class="px-2 py-2">
                                        <input type="checkbox" class="earning-checkbox rounded border-slate-300" name="earning_ids[]" value="{{ $earning->id }}" checked>
                                    </td>
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
                                    <td class="px-2 py-2">{{ number_format($earning->commission_amount, 2) }} {{ $earning->currency }}</td>
                                    <td class="px-2 py-2">{{ $earning->earned_at?->format($globalDateFormat.' H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-2 py-3 text-slate-500">No payable earnings found for the selected rep.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Payout method</label>
                    <select name="payout_method" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Not set</option>
                        <option value="bank">Bank</option>
                        <option value="mobile">Mobile</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Note</label>
                    <input name="note" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional note" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create payout draft</button>
                <a href="{{ route('admin.commission-payouts.index') }}" class="text-sm text-slate-600 hover:text-slate-800">Cancel</a>
            </div>
        </form>
    </div>
@endsection
