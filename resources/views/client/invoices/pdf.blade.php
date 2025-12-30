<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ is_numeric($invoice->number) ? $invoice->number : $invoice->id }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #0f172a; font-size: 12px; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #64748b; }
        .section { margin-top: 16px; }
        .row { display: flex; justify-content: space-between; }
        .logo-block { display: flex; flex-direction: column; gap: 6px; }
        .logo { width: 56px; height: 56px; object-fit: contain; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; padding: 6px; }
        .logo-fallback { width: 56px; height: 56px; border-radius: 12px; background: #0f172a; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; }
        th { background: #f8fafc; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    @php
        $displayNumber = is_numeric($invoice->number) ? $invoice->number : $invoice->id;
        $creditTotal = $invoice->accountingEntries->where('type', 'credit')->sum('amount');
        $paidTotal = $invoice->accountingEntries->where('type', 'payment')->sum('amount');
        $balance = max(0, (float) $invoice->total - $paidTotal - $creditTotal);
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

    <div class="row">
        <div class="logo-block">
            @if(!empty($logoSrc))
                <img src="{{ $logoSrc }}" alt="Logo" class="logo">
            @else
                <div class="logo-fallback">Apptimatic</div>
            @endif
            <div class="muted">Invoice #{{ $displayNumber }}</div>
            <div class="muted">{{ $portalBranding['company_name'] ?? 'License Portal' }}</div>
        </div>
        <div class="right">
            <div class="muted">Status</div>
            <div>{{ ucfirst($invoice->status) }}</div>
            <div class="muted">Due {{ $invoice->due_date->format('d-m-Y') }}</div>
        </div>
    </div>

    <div class="section row">
        <div>
            <div class="muted">Invoiced to</div>
            <div><strong>{{ $invoice->customer?->name }}</strong></div>
            <div>{{ $invoice->customer?->email }}</div>
            <div class="muted">{{ $invoice->customer?->address ?: 'Address not provided.' }}</div>
        </div>
        <div>
            <div class="muted">Pay to</div>
            <div><strong>{{ $portalBranding['company_name'] ?? 'License Portal' }}</strong></div>
            <div>{{ $payToText ?: 'Billing Department' }}</div>
            <div class="muted">{{ $companyEmail ?: 'support@example.com' }}</div>
        </div>
    </div>

    <div class="section row">
        <div>
            <span class="muted">Invoice date:</span>
            <strong>{{ $invoice->issue_date->format('d-m-Y') }}</strong>
        </div>
        <div>
            <span class="muted">Service:</span>
            <strong>
                {{ $invoice->subscription?->plan?->product?->name ?? 'Service' }}
                {{ $invoice->subscription?->plan?->name ? ' - '.$invoice->subscription->plan->name : '' }}
            </strong>
        </div>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="right">{{ $invoice->currency }} {{ $item->line_total }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td class="right"><strong>Sub total</strong></td>
                    <td class="right"><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</strong></td>
                </tr>
                <tr>
                    <td class="right"><strong>Credit</strong></td>
                    <td class="right"><strong>{{ $invoice->currency }} {{ number_format((float) $creditTotal, 2) }}</strong></td>
                </tr>
                <tr>
                    <td class="right"><strong>Total</strong></td>
                    <td class="right"><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}</strong></td>
                </tr>
                <tr>
                    <td class="right"><strong>Balance</strong></td>
                    <td class="right"><strong>{{ $invoice->currency }} {{ number_format((float) $balance, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
