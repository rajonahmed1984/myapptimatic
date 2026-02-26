@extends('emails.layout')

@php
    $hasFailures = ($failedRuns ?? 0) > 0;
    $statusBg = $hasFailures ? '#fee2e2' : '#dcfce7';
    $statusColor = $hasFailures ? '#b91c1c' : '#166534';
@endphp

@section('content')
    <div style="padding:10px 16px;text-align:center;background-color:{{ $statusBg }};color:{{ $statusColor }};font-family:Arial, sans-serif;font-size:14px;">
        Apptimatic Cron Job Activity (last 24 hours)
    </div>

    <div style="font-family:Arial, sans-serif;color:#475569;margin-top:16px;text-align:center;font-size:13px;">
        Range: <span style="white-space:nowrap;">{{ $rangeStart->format($dateFormat . ' h:i A') }} - {{ $rangeEnd->format($dateFormat . ' h:i A') }} ({{ $timeZone }})</span>
    </div>

    <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:16px;font-family:Arial, sans-serif;color:#475569;font-size:13px;">
        <tbody>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;width:60%;">Total runs</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $totalRuns }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">Failures</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $failedRuns }}
            </td>
        </tr>
        <tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;">Missed runs</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                {{ $missedRuns }}
            </td>
        </tr>
        </tbody>
    </table>

    @if($missedRuns > 0)
        <div style="margin-top:16px;padding:12px 14px;background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;font-family:Arial, sans-serif;font-size:13px;">
            One or more scheduled commands did not run in the last 24 hours.
        </div>
    @endif

    <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:18px;font-family:Arial, sans-serif;color:#475569;font-size:12px;">
        <thead>
        <tr>
            <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">Command</th>
            <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">Last run</th>
            <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">Status</th>
            <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">Duration</th>
            <th align="left" style="padding:8px;border-bottom:1px solid #e2e8f0;">Output</th>
        </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td style="padding:8px;border-bottom:1px solid #f1f5f9;">{{ $row['command'] }}</td>
                <td style="padding:8px;border-bottom:1px solid #f1f5f9;white-space:nowrap;">
                    <span style="white-space:nowrap;">{{ $row['last_run_at']?->format($dateFormat . ' h:i A') ?? '--' }}</span>
                </td>
                <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                    {{ ucfirst($row['status'] ?? 'unknown') }}
                </td>
                <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                    {{ $row['duration'] }}
                </td>
                <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#64748b;">
                    {{ $row['output'] }}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="padding:12px 16px;text-align:center;background-color:#fff3d2;color:#b7973d;margin-top:16px;font-family:Arial, sans-serif;font-size:13px;">
        Report generated for {{ $companyName }}.
    </div>
@endsection
