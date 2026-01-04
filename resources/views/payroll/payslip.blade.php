@php
    use App\Models\Setting;

    $companyName = $companyName ?? (Setting::getValue('company_name') ?: config('app.name'));
    $companyAddress = $companyAddress ?? Setting::getValue('company_address');

    $formatMoney = fn ($value) => number_format((float) $value, 2, '.', '');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - {{ $companyName }}</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; background: #f8fafc; }
        .payslip { max-width: 820px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        h1 { margin: 0; font-size: 22px; color: #0f172a; }
        h2 { margin: 12px 0 6px; font-size: 16px; color: #0f172a; }
        .muted { color: #64748b; font-size: 12px; }
        .row { display: flex; gap: 16px; margin-top: 12px; }
        .col { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        th { text-align: left; color: #475569; background: #f8fafc; }
        .summary { margin-top: 14px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f1f5f9; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; background: #e2e8f0; color: #334155; }
        .text-right { text-align: right; }
        .footer { margin-top: 20px; font-size: 12px; color: #475569; }
    </style>
</head>
<body>
    <div class="payslip">
        <div class="row" style="align-items: flex-start;">
            <div class="col">
                <h1>{{ $companyName }}</h1>
                @if($companyAddress)
                    <div class="muted">{{ $companyAddress }}</div>
                @endif
            </div>
            <div class="col text-right">
                <div class="badge">Payroll Month</div>
                <div style="font-weight:600; margin-top:4px;">{{ $periodLabel ?? ($period_start->format('F Y') ?? '') }}</div>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <h2>Employee</h2>
                <div>{{ $employee->name ?? 'Employee' }}</div>
                <div class="muted">ID: {{ $employee->employee_code ?? $employee->id ?? '—' }}</div>
                <div class="muted">Designation: {{ $employee->designation ?? '—' }}</div>
            </div>
            <div class="col">
                <h2>Pay Period</h2>
                <div>{{ optional($period_start)->format('d M Y') }} — {{ optional($period_end)->format('d M Y') }}</div>
                <div class="muted">Payment date: {{ optional($payment_date)->format('d M Y') ?: '—' }}</div>
                <div class="muted">Payment ref: {{ $payment_reference ?? '—' }}</div>
            </div>
        </div>

        <h2>Earnings</h2>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount (BDT)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic salary</td>
                    <td class="text-right">{{ $formatMoney($item->base_pay ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Pro-rata adjustment</td>
                    <td class="text-right">{{ $formatMoney($item->pro_rata_adjustment ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Overtime</td>
                    <td class="text-right">{{ $formatMoney($item->overtime_amount ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Bonus</td>
                    <td class="text-right">{{ $formatMoney($item->bonus_amount ?? 0) }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Deductions</h2>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount (BDT)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Unpaid leave</td>
                    <td class="text-right">{{ $formatMoney($item->unpaid_leave_deduction ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Salary advances</td>
                    <td class="text-right">{{ $formatMoney($item->advance_deduction ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Penalties</td>
                    <td class="text-right">{{ $formatMoney($item->penalty_amount ?? 0) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary">
            <div class="row">
                <div class="col">Gross pay</div>
                <div class="col text-right" style="font-weight:600;">{{ $formatMoney($item->gross_pay ?? 0) }}</div>
            </div>
            <div class="row" style="margin-top:6px;">
                <div class="col">Total deductions</div>
                <div class="col text-right">{{ $formatMoney($item->total_deductions ?? 0) }}</div>
            </div>
            <div class="row" style="margin-top:6px;">
                <div class="col">Net pay</div>
                <div class="col text-right" style="font-weight:700; color:#0f172a;">{{ $formatMoney($item->net_pay ?? 0) }}</div>
            </div>
        </div>

        <div class="footer">
            <div>Disclaimer: This payslip is system-generated and does not require a signature.</div>
        </div>
    </div>
</body>
</html>
