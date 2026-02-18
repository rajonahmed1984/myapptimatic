<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Apptimatic - {{ ucfirst($invoice->status) }} Invoice</title>
    <style>
        @page { size: auto; margin: 20px; padding: 0; }
        * { box-sizing: border-box; }

        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.42857143;
            color: #333;
            margin: 0;
            padding: 10px;
            background: #fff;
        }

        .invoice-container { width: 100%; background: #fff; padding: 0; color: #333; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .invoice-container .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }
        .invoice-container .invoice-grid { display: table; width: 100%; table-layout: fixed; }
        .invoice-container .invoice-grid > .invoice-col { display: table-cell; width: 50%; vertical-align: top; }
        .invoice-container .invoice-col { width: 50%; padding: 0 15px; }
        .invoice-container .invoice-col.full { width: 100%; }
        .invoice-container .logo-wrap { display: flex; align-items: center; }
        .invoice-container .invoice-logo { width: 300px; max-width: 100%; height: auto; }
        .invoice-container .invoice-logo-fallback { font-size: 54px; font-weight: 800; color: #211f75; letter-spacing: -1px; line-height: 1; }
        .invoice-container .invoice-col.right { text-align: right; }
        .invoice-container .invoice-status { margin: 20px 0 0; font-size: 24px; font-weight: bold; }
        .invoice-container .invoice-status h3 { margin: 0; font-size: 18px; font-weight: 600; }
        .invoice-container .invoice-status .small-text { font-size: 0.92em; }
        .invoice-container hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
        .invoice-container address { margin: 8px 0 0; font-style: normal; line-height: 1.5; }
        .invoice-container .small-text { font-size: 0.92em; }
        .invoice-container .panel { margin-top: 14px; background: #fff; }
        .invoice-container .panel-heading { padding: 0 0 8px; background: transparent; border: 0; }
        .invoice-container .panel-body { padding: 0; border: 0; }
        .invoice-container .panel-title { margin: 0; font-size: 16px; }
        .invoice-container .table-responsive { width: 100%; overflow-x: auto; }
        .invoice-container .table { width: 100%; max-width: 100%; margin-bottom: 0; border-collapse: collapse; }
        .invoice-container .table > thead > tr > td,
        .invoice-container .table > tbody > tr > td { border: 1px solid #ddd; padding: 8px; line-height: 1.42857143; vertical-align: top; }
        .invoice-container .text-right { text-align: right; }
        .invoice-container .text-center { text-align: center; }
        .invoice-container .unpaid, .invoice-container .overdue { color: #cc0000; text-transform: uppercase; }
        .invoice-container .paid { color: #779500; text-transform: uppercase; }
        .invoice-container .cancelled, .invoice-container .refunded { color: #888; text-transform: uppercase; }
        .invoice-container .mt-5 { margin-top: 50px; }
    </style>
</head>
<body>
    @php
        $displayNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $creditTotal = (float) $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $taxSetting = \App\Models\TaxSetting::current();
        $taxLabel = $taxSetting->invoice_tax_label ?: 'Tax';
        $hasTax = $invoice->tax_amount !== null && $invoice->tax_rate_percent !== null && $invoice->tax_mode;
        $discountAmount = $creditTotal;
        $payableAmount = max(0, (float) $invoice->total - $discountAmount);
        $statusClass = strtolower((string) $invoice->status);

        $companyName = $portalBranding['company_name'] ?? config('app.name', 'Apptimatic');
        $companyEmail = $companyEmail ?? 'support@example.com';
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

    <div class="invoice-container">
        <div class="invoice-grid invoice-header">
            <div class="invoice-col logo-wrap">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" title="{{ $companyName }}" class="invoice-logo" />
                @else
                    <div class="invoice-logo-fallback">{{ strtolower($companyName) }}</div>
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

        <div class="row mt-5">
            <div class="invoice-col full text-center">
                <p>This is system generated invoice no signature required</p>
            </div>
        </div>
    </div>
</body>
</html>
