@extends('layouts.client')

@section('title', 'Invoice')
@section('page-title', 'Invoice')

@section('content')
    @php
        $displayNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $taxSetting = \App\Models\TaxSetting::current();
        $taxLabel = $taxSetting->invoice_tax_label ?: 'Tax';
        $taxNote = $taxSetting->renderNote($invoice->tax_rate_percent);
        $hasTax = $invoice->tax_amount !== null && $invoice->tax_rate_percent !== null && $invoice->tax_mode;
        $discountAmount = $creditTotal;
        $payableAmount = max(0, (float) $invoice->total - $discountAmount);
        $statusClass = strtolower((string) $invoice->status);

        $companyName = $portalBranding['company_name'] ?? config('app.name', 'Apptimatic');
        $companyEmail = $companyEmail ?? 'support@example.com';
        $payToLine = $payToText ?? 'Billing Department';
        $pendingProof = $invoice->paymentProofs->firstWhere('status', 'pending');
        $rejectedProof = $invoice->paymentProofs->firstWhere('status', 'rejected');
        $downloadRoute = request()->routeIs('admin.invoices.client-view')
            ? route('admin.invoices.download', $invoice)
            : route('client.invoices.download', $invoice);
    @endphp

    <div class="invoice-container">
        <div class="invoice-grid invoice-header">
            <div class="invoice-col logo-wrap">
                @if(!empty($portalBranding['logo_url']))
                    <img src="{{ $portalBranding['logo_url'] }}" title="{{ $companyName }}" class="invoice-logo" />
                @else
                    <div class="invoice-logo-fallback">{{ strtolower($companyName) }}</div>
                @endif
            </div>
            <div class="invoice-col text-right">
                <div class="invoice-status">
                    <span class="{{ $statusClass }}" style="text-transform: uppercase;">{{ strtoupper($invoice->status) }}</span>
                    <h3>Invoice #{{ $displayNumber }}</h3>
                    <div style="margin-top: 0; font-size: 12px;">Invoice Date: <span class="small-text">{{ $invoice->issue_date->format($globalDateFormat) }}</span></div>
                    <div style="margin-top: 0; font-size: 12px;">Invoice Due Date: <span class="small-text">{{ $invoice->due_date->format($globalDateFormat) }}</span></div>
                    @if($invoice->paid_at)
                        <div style="margin-top: 0; font-size: 12px;">Paid Date: <span class="small-text">{{ $invoice->paid_at->format($globalDateFormat) }}</span></div>
                    @endif
                </div>
            </div>
        </div>

        <hr />

        <div class="invoice-grid invoice-addresses">
            <div class="invoice-col">
                <strong>Invoiced To</strong>
                <address class="small-text">
                    {{ $invoice->customer?->name ?? '--' }}<br />
                    {{ $invoice->customer?->email ?? '--' }}<br />
                    {{ $invoice->customer?->address ?? '--' }}
                </address>
            </div>
            <div class="invoice-col right">
                <strong>Pay To</strong>
                <address class="small-text">
                    {{ $companyName }}<br />
                    {{ $payToLine }}<br />
                    {{ $companyEmail }}
                </address>
            </div>
        </div>

        <div class="panel panel-default">
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
                                    <td class="total-row text-right"><strong>{{ $invoice->tax_mode === 'inclusive' ? 'Included Tax' : $taxLabel }} ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate_percent, 2, '.', ''), '0'), '.') }}%)</strong></td>
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

        @if($invoice->status !== 'paid')
            <div class="payment-panel no-print">
                <div class="payment-heading">Payment Method</div>
                @if($pendingProof)
                    <div class="alert amber">Manual payment submitted and pending review.</div>
                @elseif($rejectedProof)
                    <div class="alert rose">Manual payment was rejected. Please submit a new transfer.</div>
                @endif

                @if($gateways->isEmpty())
                    <div class="small-text text-muted">No active payment gateways configured.</div>
                @else
                    <form method="POST" action="{{ route('client.invoices.checkout', $invoice) }}" id="gateway-form" class="gateway-form" data-native="true">
                        @csrf
                        <label for="gateway-select" class="small-text"><strong>Select gateway</strong></label>
                        <select id="gateway-select" name="payment_gateway_id" class="form-control">
                            @foreach($gateways as $gateway)
                                <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                            @endforeach
                        </select>
                        <div id="gateway-instructions" class="small-text text-muted" style="margin-top: 10px;"></div>
                        <button type="submit" id="gateway-submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pay now</button>
                    </form>

                    @php
                        $gatewayData = $gateways->map(function ($gateway) {
                            return [
                                'id' => $gateway->id,
                                'name' => $gateway->name,
                                'driver' => $gateway->driver,
                                'payment_url' => $gateway->settings['payment_url'] ?? '',
                                'instructions' => $gateway->settings['instructions'] ?? '',
                                'button_label' => $gateway->settings['button_label'] ?? '',
                            ];
                        })->values();
                    @endphp
                    <script>
                        const gateways = @json($gatewayData);
                        const gatewaySelect = document.getElementById('gateway-select');
                        const gatewayInstructions = document.getElementById('gateway-instructions');
                        const gatewaySubmit = document.getElementById('gateway-submit');
                        const gatewayForm = document.getElementById('gateway-form');

                        function syncGatewayDetails() {
                            const selectedId = Number(gatewaySelect.value);
                            const selected = gateways.find((gateway) => gateway.id === selectedId);

                            if (!selected) {
                                gatewayInstructions.textContent = '';
                                if (gatewaySubmit) gatewaySubmit.textContent = 'Pay now';
                                return;
                            }

                            const instructions = selected.instructions || '';
                            gatewayInstructions.innerHTML = instructions
                                ? instructions.replace(/\n/g, '<br>')
                                : 'No additional instructions for this gateway.';

                            if (gatewaySubmit) {
                                const label = (selected.button_label || '').trim();
                                gatewaySubmit.textContent = label ? label : `${selected.name} Pay`;
                            }

                            if (gatewayForm) {
                                const openNew = selected.driver === 'bkash' && selected.payment_url;
                                gatewayForm.setAttribute('target', openNew ? '_blank' : '_self');
                            }
                        }

                        gatewaySelect.addEventListener('change', syncGatewayDetails);
                        syncGatewayDetails();
                    </script>
                @endif

                @if(!empty($paymentInstructions))
                    <div class="small-text text-muted" style="margin-top: 12px;">
                        {!! nl2br(e($paymentInstructions)) !!}
                    </div>
                @endif
            </div>
        @endif

        <div class="container-fluid invoice-container">
            <div class="row mt-5" style="display: flex !important; justify-content: center;">
                <div class="invoice-col full no-print" style="text-align: center;">
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <a href="{{ $downloadRoute }}" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Download</a>
                        <a href="javascript:window.print()" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Print</a>
                    </div>
                </div>
                <div class="invoice-col full" style="text-align: center;">
                    <div class="mb-3">
                        <p>This is system generated invoice no signature required</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        * { box-sizing: border-box; }
        .invoice-container { width: 100%; background: #fff; padding: 10px; color: #333; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .invoice-container .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }
        .invoice-container .invoice-grid { display: table; width: 100%; table-layout: fixed; }
        .invoice-container .invoice-grid > .invoice-col { display: table-cell; width: 50%; vertical-align: top; }
        .invoice-container .invoice-col { width: 50%; padding: 0 15px; }
        .invoice-container .invoice-col.full { width: 100%; }
        .invoice-container .invoice-col.right { text-align: right; }
        .invoice-container .logo-wrap { display: flex; align-items: center; }
        .invoice-container .invoice-logo { width: 300px; max-width: 100%; height: auto; }
        .invoice-container .invoice-logo-fallback { font-size: 54px; font-weight: 800; color: #211f75; letter-spacing: -1px; line-height: 1; }
        .invoice-container .invoice-status { margin: 20px 0 0; font-size: 24px; font-weight: bold; }
        .invoice-container .invoice-status h3 { margin: 0; font-size: 18px; font-weight: 600; }
        .invoice-container .small-text { font-size: 0.92em; }
        .invoice-container hr { margin: 20px 0; border: 0; border-top: 1px solid #eee; }
        .invoice-container address { margin: 8px 0 0; font-style: normal; line-height: 1.5; }
        .invoice-container .panel { margin-top: 14px; background: #fff; }
        .invoice-container .panel-heading { padding: 0 0 8px; background: transparent; border: 0; }
        .invoice-container .panel-title { margin: 0; font-size: 16px; }
        .invoice-container .table-responsive { width: 100%; overflow-x: auto; }
        .invoice-container .table { width: 100%; max-width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .invoice-container .table > thead > tr > td,
        .invoice-container .table > tbody > tr > td { padding: 8px; line-height: 1.42857143; vertical-align: top; border: 1px solid #ddd; }
        .invoice-container .text-right { text-align: right !important; }
        .invoice-container .text-center { text-align: center !important; }
        .invoice-container .mt-5 { margin-top: 50px; }
        .invoice-container .mb-3 { margin-bottom: 30px; }
        .invoice-container .unpaid, .invoice-container .overdue { color: #cc0000; }
        .invoice-container .paid { color: #779500; }
        .invoice-container .refunded { color: #224488; }
        .invoice-container .cancelled { color: #888; }
        .invoice-container .text-muted { color: #666; }
        .payment-panel { border: 1px solid #ddd; padding: 12px; margin-top: 18px; }
        .payment-heading { font-weight: 700; margin-bottom: 8px; }
        .gateway-form .form-control { width: 100%; border: 1px solid #ccc; padding: 8px; margin-top: 6px; }
        .btn-primary { margin-top: 10px; border: 1px solid #0f766e; background: #0f766e; color: #fff; padding: 8px 14px; border-radius: 3px; cursor: pointer; }
        .btn-default { border: 1px solid #ccc; background: #fff; color: #333; padding: 6px 12px; text-decoration: none; display: inline-block; }
        .btn-group .btn-default + .btn-default { margin-left: -1px; }
        .alert { padding: 8px 10px; margin-bottom: 10px; border: 1px solid transparent; }
        .alert.amber { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
        .alert.rose { border-color: #fecdd3; background: #fff1f2; color: #9f1239; }
        @media (max-width: 767px) {
            .invoice-container .invoice-col { padding: 0 10px; }
            .invoice-container .invoice-logo { width: 220px; }
        }
        @media print {
            .invoice-container .invoice-grid { display: table !important; width: 100% !important; table-layout: fixed !important; }
            .invoice-container .invoice-grid > .invoice-col { display: table-cell !important; width: 50% !important; vertical-align: top !important; }
            .no-print, .no-print * { display: none !important; }
        }
    </style>
@endpush
