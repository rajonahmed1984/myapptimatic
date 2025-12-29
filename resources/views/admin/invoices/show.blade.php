@extends('layouts.admin')

@section('title', 'Invoice Details')
@section('page-title', 'Invoice Details')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="section-label">Invoice</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $invoice->number }}</div>
                <div class="mt-1 text-sm text-slate-500">Customer: {{ $invoice->customer->name }}</div>
            </div>
            <div class="text-sm text-slate-600">
                <div>Status: {{ ucfirst($invoice->status) }}</div>
                <div>Due: {{ $invoice->due_date->format('d-m-Y') }}</div>
            </div>
        </div>

        <div class="mt-6 space-y-3">
            @foreach($invoice->items as $item)
                <div class="flex items-center justify-between border-b border-slate-200 pb-2 text-sm">
                    <span>{{ $item->description }}</span>
                    <span>{{ $invoice->currency }} {{ $item->line_total }}</span>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.invoices.update', $invoice) }}" class="mt-6 grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            <div>
                <label class="text-sm text-slate-600">Status</label>
                <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    <option value="unpaid" @selected($invoice->status === 'unpaid')>Unpaid</option>
                    <option value="overdue" @selected($invoice->status === 'overdue')>Overdue</option>
                    <option value="paid" @selected($invoice->status === 'paid')>Paid</option>
                    <option value="cancelled" @selected($invoice->status === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Issue date</label>
                <input name="issue_date" type="date" value="{{ old('issue_date', $invoice->issue_date->format('Y-m-d')) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Due date</label>
                <input name="due_date" type="date" value="{{ old('due_date', $invoice->due_date->format('Y-m-d')) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Notes</label>
                <textarea name="notes" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes', $invoice->notes) }}</textarea>
                <p class="mt-2 text-xs text-slate-500">Use "Recalculate" to update totals after changing dates.</p>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Save invoice</button>
            </div>
        </form>

        <div class="mt-4 text-right text-lg font-semibold text-teal-600">
            Total: {{ $invoice->currency }} {{ $invoice->total }}
        </div>

        @if($invoice->accountingEntries->isNotEmpty())
            <div class="mt-6">
                <div class="section-label">Accounting entries</div>
                <div class="mt-3 space-y-2 text-sm">
                    @foreach($invoice->accountingEntries as $entry)
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <div>
                                <div class="font-semibold text-slate-900">{{ ucfirst($entry->type) }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $entry->entry_date->format('d-m-Y') }}
                                    @if($entry->paymentGateway)
                                        · {{ $entry->paymentGateway->name }}
                                    @endif
                                </div>
                            </div>
                            <div class="font-semibold {{ $entry->isOutflow() ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ $entry->isOutflow() ? '-' : '+' }}{{ $entry->currency }} {{ number_format((float) $entry->amount, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($invoice->paymentProofs->isNotEmpty())
            <div class="mt-6">
                <div class="section-label">Manual payment submissions</div>
                <div class="mt-3 space-y-3 text-sm">
                    @foreach($invoice->paymentProofs as $proof)
                        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $proof->paymentGateway?->name ?? 'Manual' }}</div>
                                    <div class="text-xs text-slate-500">
                                        Amount: {{ $invoice->currency }} {{ number_format((float) $proof->amount, 2) }}
                                        • Reference: {{ $proof->reference ?: '--' }}
                                        • Status: {{ ucfirst($proof->status) }}
                                    </div>
                                    @if($proof->paid_at)
                                        <div class="text-xs text-slate-500">Paid at: {{ $proof->paid_at->format('d-m-Y') }}</div>
                                    @endif
                                    @if($proof->notes)
                                        <div class="mt-2 text-xs text-slate-500">{{ $proof->notes }}</div>
                                    @endif
                                    @if($proof->reviewer)
                                        <div class="mt-2 text-xs text-slate-400">Reviewed by {{ $proof->reviewer->name }}</div>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($proof->attachment_path)
                                        <a href="{{ asset('storage/'.$proof->attachment_path) }}" target="_blank" rel="noopener" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">View receipt</a>
                                    @endif
                                    @if($proof->status === 'pending')
                                        <form method="POST" action="{{ route('admin.payment-proofs.approve', $proof) }}">
                                            @csrf
                                            <button type="submit" class="rounded-full bg-emerald-500 px-4 py-2 text-xs font-semibold text-white">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.payment-proofs.reject', $proof) }}">
                                            @csrf
                                            <button type="submit" class="rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:border-rose-300">Reject</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            @if($invoice->status !== 'paid')
                <a href="{{ route('admin.accounting.create', ['type' => 'payment', 'invoice_id' => $invoice->id]) }}" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Record payment</a>
                <form method="POST" action="{{ route('admin.invoices.recalculate', $invoice) }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Recalculate</button>
                </form>
            @else
                <a href="{{ route('admin.accounting.create', ['type' => 'refund', 'invoice_id' => $invoice->id]) }}" class="rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Record refund</a>
            @endif
        </div>
    </div>
@endsection
