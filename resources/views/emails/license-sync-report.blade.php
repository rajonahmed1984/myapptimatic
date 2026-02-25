@extends('emails.layout')

@php
    $hasRun = $hasRun ?? false;
    $statusBg = $hasRun ? '#dcfce7' : '#fee2e2';
    $statusColor = $hasRun ? '#166534' : '#b91c1c';
@endphp

@section('content')
    <div style="padding:10px 16px;text-align:center;background-color:{{ $statusBg }};color:{{ $statusColor }};font-family:Arial, sans-serif;font-size:14px;">
        Apptimatic Licenses Synchronisation Cron Report
    </div>

    <div style="font-family:Arial, sans-serif;color:#475569;margin-top:16px;text-align:center;font-size:13px;">
        Range: {{ $rangeStart->format($dateFormat . ' h:i A') }} - {{ $rangeEnd->format($dateFormat . ' h:i A') }} ({{ $timeZone }})
    </div>

    @if(! $hasRun)
        <div style="margin-top:16px;padding:12px 14px;background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;font-family:Arial, sans-serif;font-size:13px;">
            No synchronisation run recorded today.
        </div>
    @endif

    <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:16px;font-family:Arial, sans-serif;color:#475569;font-size:13px;">
        <tbody>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">Total licenses checked</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $counts['total_checked'] ?? 0 }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">Updated licenses</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $counts['updated_count'] ?? 0 }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">Expired marked</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $counts['expired_count'] ?? 0 }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">Suspended/invalid</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ ($counts['suspended_count'] ?? 0) + ($counts['invalid_count'] ?? 0) }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">Domain bindings updated</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $counts['domain_updates_count'] ?? 0 }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">Domain mismatches</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $counts['domain_mismatch_count'] ?? 0 }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">API verification failures</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $counts['api_failures_count'] ?? 0 }}
            </td>
        </tr>
        </tbody>
    </table>

    @if(!empty($details))
        <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:18px;font-family:Arial, sans-serif;color:#475569;font-size:12px;">
            <thead>
            <tr>
                <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">License</th>
                <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">Customer</th>
                <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">Change/Issue</th>
                <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">Reason</th>
            </tr>
            </thead>
            <tbody>
            @foreach($details as $detail)
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                        {{ $detail['license_key'] ?? $detail['license_id'] ?? '--' }}
                    </td>
                    <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                        {{ $detail['customer'] ?? '--' }}
                    </td>
                    <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                        @if(($detail['previous_status'] ?? null) && ($detail['new_status'] ?? null))
                            {{ $detail['previous_status'] }} -> {{ $detail['new_status'] }}
                        @else
                            {{ $detail['domain'] ?? 'Verification error' }}
                        @endif
                    </td>
                    <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#64748b;">
                        {{ $detail['reason'] ?? '--' }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div style="padding:12px 16px;text-align:center;background-color:#fff3d2;color:#b7973d;margin-top:16px;font-family:Arial, sans-serif;font-size:13px;">
        Report generated for {{ $companyName }}.
    </div>
@endsection
