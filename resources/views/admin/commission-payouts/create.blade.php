@extends('layouts.admin')

@section('title', 'New Commission Payout')
@section('page-title', 'New Commission Payout')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Commissions</div>
            <h1 class="text-2xl font-semibold text-slate-900">Create payout</h1>
            <div class="text-sm text-slate-500">Select a sales rep and payable earnings to include.</div>
        </div>
        <a href="{{ route('admin.commission-payouts.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" hx-boost="false">Back to payouts</a>
    </div>

    <div class="card p-6 space-y-6">

        <form method="GET" action="{{ route('admin.commission-payouts.create') }}" class="grid gap-3 md:grid-cols-3">
            <div>
                <label class="text-xs text-slate-500">Sales rep</label>
                <select name="sales_rep_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach($salesReps as $rep)
                        <option value="{{ $rep->id }}" @selected($selectedRep == $rep->id)>
                            {{ $rep->name }} @if($rep->status !== 'active') ({{ ucfirst($rep->status) }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        @if(!empty($repBalance))
            @php
                $payableGross = (float) ($repBalance['payable_gross'] ?? 0);
                $advancePaid = (float) ($repBalance['advance_paid'] ?? 0);
                $overpaid = (float) ($repBalance['overpaid'] ?? 0);
                $netPayable = (float) ($repBalance['payable_balance'] ?? 0);
            @endphp
            <div class="rounded-2xl border border-slate-300 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-sm font-semibold text-slate-800">Advance adjustment</div>
                <div class="mt-2 grid gap-2 md:grid-cols-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Gross Payable</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ number_format($payableGross, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Advance Paid</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ number_format($advancePaid, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Overpaid</div>
                        <div class="mt-1 font-semibold {{ $overpaid > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($overpaid, 2) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Net Payable</div>
                        <div class="mt-1 font-semibold {{ $netPayable > 0 ? 'text-emerald-700' : 'text-slate-900' }}">{{ number_format($netPayable, 2) }}</div>
                    </div>
                </div>
                @if($netPayable <= 0)
                    <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                        Advance payments already cover earned commission. No payout can be created until new earnings arrive.
                    </div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('admin.commission-payouts.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="sales_rep_id" value="{{ $selectedRep }}">
            <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
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
                                <tr class="border-t border-slate-300">
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
                    @php
                        $paymentMethods = \App\Models\PaymentMethod::dropdownOptions();
                    @endphp
                    <select name="payout_method" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Not set</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}" @selected(old('payout_method') === $method->code)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Note</label>
                    <input name="note" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Optional note" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" @disabled(!empty($repBalance) && ($repBalance['payable_balance'] ?? 0) <= 0) class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60">Create payout draft</button>
                <a href="{{ route('admin.commission-payouts.index') }}" class="text-sm text-slate-600 hover:text-slate-800">Cancel</a>
            </div>
        </form>
    </div>
@endsection
