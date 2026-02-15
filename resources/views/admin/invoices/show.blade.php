@extends('layouts.admin')

@section('title', 'Invoice Details')
@section('page-title', 'Invoice Details')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="section-label">Invoice</div>
            <div class="text-2xl font-semibold text-slate-900">Invoice #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.invoices.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to invoices</a>
            <a href="{{ route('admin.invoices.client-view', $invoice) }}" target="_blank" rel="noopener" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">View as client</a>
            <a href="{{ route('admin.invoices.download', $invoice) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Download</a>
            <button type="button" id="manage-invoice-toggle" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Manage Invoice</button>
        </div>
    </div>

    <div class="card p-6 space-y-6">
        @php
            $displayNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
            $status = strtolower((string) $invoice->status);
            $statusClass = 'text-slate-600';
            if ($status === 'unpaid' || $status === 'overdue') {
                $statusClass = 'text-rose-700';
            } elseif ($status === 'paid') {
                $statusClass = 'text-emerald-700';
            }
            $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
            $taxSetting = \App\Models\TaxSetting::current();
            $taxLabel = $taxSetting->invoice_tax_label ?: 'Tax';
            $taxNote = $taxSetting->renderNote($invoice->tax_rate_percent);
            $hasTax = $invoice->tax_amount !== null && $invoice->tax_rate_percent !== null && $invoice->tax_mode;
            $discountAmount = $creditTotal;
            $payableAmount = max(0, (float) $invoice->total - $discountAmount);
            $companyName = $portalBranding['company_name'] ?? config('app.name', 'Apptimatic');
            $companyEmail = $portalBranding['email'] ?? null;
            $payToText = $portalBranding['pay_to_text'] ?? null;
        @endphp

        <div class="invoice-container">
            <div class="row invoice-header">
                <div class="invoice-col logo-wrap">
                    @if(!empty($portalBranding['logo_url']))
                        <img src="{{ $portalBranding['logo_url'] }}" alt="Logo" class="invoice-logo">
                    @else
                        <div class="invoice-logo-fallback">{{ strtolower($companyName) }}</div>
                    @endif
                </div>
                <div class="invoice-col text-right">
                    <div class="invoice-status">
                        <span class="{{ $status }}">{{ strtoupper($invoice->status) }}</span>
                        <h3>Invoice: #{{ $displayNumber }}</h3>
                        <div>Invoice Date: <span class="small-text">{{ $invoice->issue_date->format($globalDateFormat) }}</span></div>
                        <div>Invoice Due Date: <span class="small-text">{{ $invoice->due_date->format($globalDateFormat) }}</span></div>
                        @if($invoice->paid_at)
                            <div>Paid Date: <span class="small-text">{{ $invoice->paid_at->format($globalDateFormat) }}</span></div>
                        @endif
                    </div>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="invoice-col">
                    <strong>Invoiced To</strong>
                    <address class="small-text">
                        {{ $invoice->customer?->name ?? '--' }}<br>
                        {{ $invoice->customer?->email ?? '--' }}<br>
                        {{ $invoice->customer?->address ?? '--' }}
                    </address>
                </div>
                <div class="invoice-col right">
                    <strong>Pay To</strong>
                    <address class="small-text">
                        {{ $companyName }}<br>
                        {{ $payToText ?: 'Billing Department' }}<br>
                        {{ $companyEmail ?: '--' }}
                    </address>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><strong>Invoice Items</strong></h3>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-condensed">
                            <thead>
                                <tr>
                                    <td><strong>Description</strong></td>
                                    <td width="20%" class="text-center"><strong>Amount</strong></td>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->items as $item)
                                    <tr>
                                        <td>{{ $item->description }}</td>
                                        <td class="text-center">{{ $invoice->currency }} {{ number_format((float) $item->line_total, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="total-row text-right"><strong>Sub Total</strong></td>
                                    <td class="total-row text-center">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
                                </tr>
                                @if($hasTax)
                                    <tr>
                                        <td class="total-row text-right"><strong>{{ $invoice->tax_mode === 'inclusive' ? 'Included '.$taxLabel : $taxLabel }} ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate_percent, 2, '.', ''), '0'), '.') }}%)</strong></td>
                                        <td class="total-row text-center">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="total-row text-right"><strong>Discount</strong></td>
                                    <td class="total-row text-center">{{ $discountAmount > 0 ? '- '.$invoice->currency.' '.number_format($discountAmount, 2) : '- '.$invoice->currency.' 0.00' }}</td>
                                </tr>
                                <tr>
                                    <td class="total-row text-right"><strong>Payable Amount</strong></td>
                                    <td class="total-row text-center">{{ $invoice->currency }} {{ number_format($payableAmount, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($hasTax && $taxNote)
                <div class="mt-2 text-right text-xs text-slate-500">{{ $taxNote }}</div>
            @endif

            <div class="row mt-5">
                <div class="invoice-col full text-center">
                    <p>This is system generated invoice no signature required</p>
                </div>
            </div>
        </div>

        <div id="manage-invoice-panel" class="hidden rounded-2xl border border-slate-200 bg-white/90 p-4">
            <div class="mb-3 text-sm font-semibold text-slate-800">Manage Invoice</div>
            <form method="POST" action="{{ route('admin.invoices.update', $invoice) }}" class="grid gap-4 md:grid-cols-2">
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
                                    {{ $entry->entry_date->format($globalDateFormat) }}
                                    @if($entry->paymentGateway)
                                        • {{ $entry->paymentGateway->name }}
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
                                        <div class="text-xs text-slate-500">Paid at: {{ $proof->paid_at->format($globalDateFormat) }}</div>
                                    @endif
                                    @if($proof->notes)
                                        <div class="mt-2 text-xs text-slate-500">{{ $proof->notes }}</div>
                                    @endif
                                    @if($proof->reviewer)
                                        <div class="mt-2 text-xs text-slate-400">Reviewed by {{ $proof->reviewer->name }}</div>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($proof->attachment_url)
                                        <a href="{{ $proof->attachment_url }}" target="_blank" rel="noopener" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">View receipt</a>
                                    @elseif($proof->attachment_path)
                                        <span class="text-xs font-semibold text-slate-400">Receipt unavailable</span>
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

        <div class="mt-6 flex flex-wrap justify-center gap-3">
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

@push('styles')
    <style>
        .invoice-container { width: 100%; background: #fff; padding: 10px; color: #333; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .invoice-container .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }
        .invoice-container .invoice-col { width: 50%; padding: 0 15px; }
        .invoice-container .invoice-col.full { width: 100%; }
        .invoice-container .invoice-col.right { text-align: right; }
        .invoice-container .logo-wrap { display: flex; align-items: center; }
        .invoice-container .invoice-logo { width: 300px; max-width: 100%; height: auto; }
        .invoice-container .invoice-logo-fallback { font-size: 54px; font-weight: 800; color: #211f75; letter-spacing: -1px; line-height: 1; }
        .invoice-container .invoice-status { margin-top: 8px; font-size: 24px; font-weight: 700; }
        .invoice-container .invoice-status h3 { margin: 0; font-size: 18px; font-weight: 600; }
        .invoice-container .invoice-status .small-text { font-size: 12px; }
        .invoice-container hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
        .invoice-container address { margin: 8px 0 0; font-style: normal; line-height: 1.5; }
        .invoice-container .small-text { font-size: 0.92em; }
        .invoice-container .panel { margin-top: 14px; background: #fff; }
        .invoice-container .panel-heading { padding: 10px 15px; background: #f5f5f5; border: 1px solid #ddd; }
        .invoice-container .panel-title { margin: 0; font-size: 16px; }
        .invoice-container .table-responsive { width: 100%; overflow-x: auto; }
        .invoice-container .table { width: 100%; border-collapse: collapse; }
        .invoice-container .table > thead > tr > td,
        .invoice-container .table > tbody > tr > td { border: 1px solid #ddd; padding: 8px; }
        .invoice-container .text-right { text-align: right; }
        .invoice-container .text-center { text-align: center; }
        .invoice-container .unpaid, .invoice-container .overdue { color: #cc0000; text-transform: uppercase; }
        .invoice-container .paid { color: #779500; text-transform: uppercase; }
        .invoice-container .cancelled, .invoice-container .refunded { color: #888; text-transform: uppercase; }
        .invoice-container .mt-5 { margin-top: 50px; }
        @media (max-width: 767px) {
            .invoice-container .invoice-col { width: 100%; }
            .invoice-container .invoice-col.right { text-align: left; margin-top: 14px; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('manage-invoice-toggle');
            const panel = document.getElementById('manage-invoice-panel');
            if (!toggle || !panel) return;

            toggle.addEventListener('click', () => {
                panel.classList.toggle('hidden');
                if (!panel.classList.contains('hidden')) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
@endpush
