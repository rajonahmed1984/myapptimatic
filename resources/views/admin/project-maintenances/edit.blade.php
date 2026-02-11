@extends('layouts.admin')

@section('title', 'Edit Maintenance')
@section('page-title', 'Edit Maintenance')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Projects</div>
            <div class="text-2xl font-semibold text-slate-900">Edit maintenance</div>
            <div class="text-sm text-slate-500">Update plan details or pause/cancel billing.</div>
        </div>
        <a href="{{ route('admin.project-maintenances.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to maintenance</a>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Status</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucfirst($maintenance->status) }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Next Billing</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $maintenance->next_billing_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Amount</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $maintenance->currency }} {{ number_format((float) $maintenance->amount, 2) }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="card p-6 md:col-span-2">
            <form method="POST" action="{{ route('admin.project-maintenances.update', $maintenance) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="text-xs text-slate-500">Project</label>
                    <div class="mt-1 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        {{ $maintenance->project?->name ?? '--' }} ({{ $maintenance->customer?->name ?? 'No customer' }})
                    </div>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Title</label>
                    <input name="title" value="{{ old('title', $maintenance->title) }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Amount</label>
                        <input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $maintenance->amount) }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Billing cycle</label>
                        <select name="billing_cycle" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="monthly" @selected(old('billing_cycle', $maintenance->billing_cycle) === 'monthly')>Monthly</option>
                            <option value="yearly" @selected(old('billing_cycle', $maintenance->billing_cycle) === 'yearly')>Yearly</option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Sales representatives</label>
                        <div class="mt-2 space-y-2 rounded-2xl border border-slate-300 bg-white/80 p-3">
                            @if($salesReps->isEmpty())
                                <div class="text-xs text-slate-500">No active sales representatives found.</div>
                            @else
                                @foreach($salesReps as $rep)
                                    @php
                                        $selectedSalesReps = collect(old('sales_rep_ids', $maintenance->salesRepresentatives->pluck('id')->all()));
                                        $linkedRep = $maintenance->salesRepresentatives->firstWhere('id', $rep->id);
                                        $repAmount = old('sales_rep_amounts.'.$rep->id, $linkedRep?->pivot?->amount ?? 0);
                                    @endphp
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <label class="flex items-center gap-2 text-xs text-slate-600">
                                            <input type="checkbox" name="sales_rep_ids[]" value="{{ $rep->id }}" @checked($selectedSalesReps->contains($rep->id))>
                                            <span>{{ $rep->name }} ({{ $rep->email }})</span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-slate-500">Amount</span>
                                            <input type="number" min="0" step="0.01" name="sales_rep_amounts[{{ $rep->id }}]" value="{{ $repAmount }}" class="w-28 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs">
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Amounts apply only to selected sales reps.</p>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Status</label>
                        <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="active" @selected(old('status', $maintenance->status) === 'active')>Active</option>
                            <option value="paused" @selected(old('status', $maintenance->status) === 'paused')>Paused</option>
                            <option value="cancelled" @selected(old('status', $maintenance->status) === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Start date</label>
                        <input name="start_date" type="date" value="{{ old('start_date', $maintenance->start_date?->format('Y-m-d')) }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                            <input type="hidden" name="auto_invoice" value="0">
                            <input type="checkbox" name="auto_invoice" value="1" @checked(old('auto_invoice', $maintenance->auto_invoice))>
                            <span>Auto-generate invoice</span>
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="hidden" name="sales_rep_visible" value="0">
                    <input type="checkbox" name="sales_rep_visible" value="1" @checked(old('sales_rep_visible', $maintenance->sales_rep_visible))>
                    <span>Visible to sales reps</span>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('admin.project-maintenances.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                    <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save changes</button>
                </div>
            </form>
        </div>
        <div class="card p-6">
            <div class="text-sm font-semibold text-slate-800 mb-3">Invoice History</div>
            @php $invoices = $maintenance->invoices ?? collect(); @endphp
            @if($invoices->isEmpty())
                <div class="text-sm text-slate-600">No maintenance invoices yet.</div>
            @else
                <ul class="space-y-2 text-sm text-slate-700">
                    @foreach($invoices as $invoice)
                        <li>
                            <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.invoices.show', $invoice) }}">
                                #{{ $invoice->number ?? $invoice->id }}
                            </a>
                            <div class="text-xs text-slate-500">{{ $invoice->issue_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
