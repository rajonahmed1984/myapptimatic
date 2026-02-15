<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Apptimatic - {{ ucfirst($invoice->status) }} Invoice</title>
    <style>
        @page { size: auto; margin: 0; padding: 0; }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.42857143;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }
        * { box-sizing: border-box; }
        .container-fluid { width: 100%; margin: 0 auto; }
        .invoice-container { max-width: 1800px; padding: 14px 20px 0; }
        .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }
        .invoice-col { width: 50%; padding: 0 15px; }
        .invoice-col.right { text-align: right; }
        .invoice-col img { width: 300px; max-width: 100%; }
        .invoice-status { margin: 20px 0 0; font-size: 24px; font-weight: bold; }
        .invoice-status h3 { margin: 0; font-size: 18px; font-weight: 600; }
        .small-text { font-size: 0.9em; }
        hr { margin: 20px 0; border: 0; border-top: 1px solid #eee; }
        address { margin-bottom: 20px; font-style: normal; line-height: 1.42857143; }
        .panel { margin-bottom: 20px; background-color: #fff; }
        .panel-default > .panel-heading {
            color: #333;
            background-color: #f5f5f5;
            border-top: 1px solid #ddd;
            border-right: 1px solid #ddd;
            border-left: 1px solid #ddd;
            padding: 10px 15px;
        }
        .panel-title { margin: 0; font-size: 16px; }
        .table-responsive { width: 100%; overflow-x: auto; }
        .table { width: 100%; max-width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .table > thead > tr > td,
        .table > tbody > tr > td { padding: 8px; line-height: 1.42857143; vertical-align: top; border: 1px solid #ddd; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .mt-5 { margin-top: 50px; }
        .mb-3 { margin-bottom: 30px; }
        .unpaid, .overdue { color: #cc0000; }
        .paid { color: #779500; }
        .refunded { color: #224488; }
        .cancelled { color: #888; }
    </style>
</head>
<body>
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
        $companyEmail = $portalBranding['email'] ?? ($companyEmail ?? 'support@example.com');
        $payToLine = $payToText ?? 'Billing Department';

        $logoSrc = null;
        $logoUrl = $portalBranding['logo_url'] ?? null;

        if (is_string($logoUrl) && $logoUrl !== '') {
            if (str_starts_with($logoUrl, 'data:')) {
                $logoSrc = $logoUrl;
            } else {
                $path = parse_url($logoUrl, PHP_URL_PATH);
                if (is_string($path)) {
                    $path = ltrim($path, '/');
                    $filePath = null;

                    if (str_starts_with($path, 'storage/')) {
                        $filePath = public_path($path);
                    } elseif (str_starts_with($path, 'branding/')) {
                        $relativePath = substr($path, strlen('branding/'));
                        $filePath = storage_path('app/public/' . $relativePath);
                    }

                    if ($filePath && is_file($filePath)) {
                        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                        $mime = match ($ext) {
                            'jpg', 'jpeg' => 'image/jpeg',
                            'gif' => 'image/gif',
                            'svg' => 'image/svg+xml',
                            default => 'image/png',
                        };
                        $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($filePath));
                    }
                }
            }
        }
    @endphp

    <div class="container-fluid invoice-container">
        <div class="row invoice-header">
            <div class="invoice-col" style="display: flex;align-items: center;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" title="{{ $companyName }}" />
                @else
                    <div style="font-size: 54px; font-weight: 800; color: #211f75; letter-spacing: -1px;">{{ strtolower($companyName) }}</div>
                @endif
            </div>
            <div class="invoice-col text-right">
                <div class="invoice-status">
                    <span class="{{ $statusClass }}" style="text-transform: uppercase;">{{ strtoupper($invoice->status) }}</span>
                    <h3>Invoice: #{{ $displayNumber }}</h3>
                    <div style="margin-top: 0; font-size: 12px;">Invoice Date: <span class="small-text">{{ $invoice->issue_date->format($globalDateFormat) }}</span></div>
                    <div style="margin-top: 0; font-size: 12px;">Invoice Due Date: <span class="small-text">{{ $invoice->due_date->format($globalDateFormat) }}</span></div>
                    @if($invoice->paid_at)
                        <div style="margin-top: 0; font-size: 12px;">Paid Date: <span class="small-text">{{ $invoice->paid_at->format($globalDateFormat) }}</span></div>
                    @endif
                </div>
            </div>
        </div>

        <hr />

        <div class="row">
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

        <br />

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
            <div class="text-right small-text" style="margin-top: -6px; margin-bottom: 18px;">{{ $taxNote }}</div>
        @endif

        <div class="container-fluid invoice-container" style="padding: 0;">
            <div class="row mt-5" style="display: flex !important; justify-content: center;">
                <div class="invoice-col" style="width: 100%; text-align: center;">
                    <div class="mb-3">
                        <p>This is system generated invoice no signature required</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
