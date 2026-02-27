<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $slip_title ?? 'Payment Slip' }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 24px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
        .title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 4px; }
        .sub { font-size: 11px; color: #475569; margin: 0; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .grid td { padding: 8px 10px; border: 1px solid #e2e8f0; vertical-align: top; }
        .label { width: 35%; background: #f8fafc; font-weight: 700; color: #0f172a; }
        .footer { margin-top: 22px; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">{{ $slip_title ?? 'Payment Slip' }}</p>
        <p class="sub">{{ $company_name ?? config('app.name') }} | Generated at {{ $generated_at ?? now()->format('d-m-Y h:i A') }}</p>
    </div>

    <table class="grid">
        <tr><td class="label">Employee Name</td><td>{{ $employee_name ?? '--' }}</td></tr>
        <tr><td class="label">Employee Email</td><td>{{ $employee_email ?? '--' }}</td></tr>
        <tr><td class="label">Payment Type</td><td>{{ $payment_type ?? '--' }}</td></tr>
        <tr><td class="label">Payment Date</td><td>{{ $payment_date ?? '--' }}</td></tr>
        <tr><td class="label">Payment Amount</td><td>{{ $payment_amount ?? '--' }}</td></tr>
        <tr><td class="label">Payment Method</td><td>{{ $payment_method ?? '--' }}</td></tr>
        <tr><td class="label">Reference</td><td>{{ $reference ?? '--' }}</td></tr>
        <tr><td class="label">Description</td><td>{{ $note ?? '--' }}</td></tr>
    </table>

    <div class="footer">
        This document is generated electronically and serves as a standard payslip/payment slip.
    </div>
</body>
</html>
