@extends('emails.layout')

@php
    $statusMessage = $status === 'success'
        ? 'All cron automation tasks completed successfully'
        : 'Cron automation failed. Review the details below.';
    $statusBg = $status === 'success' ? '#d4f1ce' : '#fee2e2';
    $statusColor = $status === 'success' ? '#3d841a' : '#b91c1c';
@endphp

@section('content')
    <div style="padding:10px 16px;text-align:center;background-color:{{ $statusBg }};color:{{ $statusColor }};font-family:Arial, sans-serif;font-size:14px;">
        {{ $statusMessage }}
    </div>

    <div style="font-family:Arial, sans-serif;color:#475569;margin-top:16px;">
        <div style="text-align:center;font-size:13px;">
            Run started: {{ $startedAt->format($dateFormat . ' h:i A') }} ({{ $timeZone }})
            <br>
            Run finished: {{ $finishedAt->format($dateFormat . ' h:i A') }} ({{ $timeZone }})
        </div>
    </div>

    @if(!empty($errorMessage))
        <div style="margin-top:16px;padding:12px 14px;background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;font-family:Arial, sans-serif;font-size:13px;">
            {{ $errorMessage }}
        </div>
    @endif

    <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:18px;font-family:Arial, sans-serif;color:#475569;font-size:13px;">
        <tbody>
        @foreach($metrics as $metric)
            <tr>
                <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;width:60%;">{{ $metric['label'] }}</td>
                <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;color:#0f172a;">
                    {{ $metric['value'] }}
                </td>
                <td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:left;color:#64748b;">
                    {{ $metric['subtitle'] }}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="padding:12px 16px;text-align:center;background-color:#fff3d2;color:#b7973d;margin-top:16px;font-family:Arial, sans-serif;font-size:13px;">
        Cron summary generated for {{ $companyName }}.
    </div>

    <div style="text-align:center;margin-top:16px;font-family:Arial, sans-serif;font-size:13px;color:#475569;">
        <p>For detailed information about the actions performed, view the cron status page.</p>
        <a href="{{ $portalUrl }}/admin/settings?tab=cron" style="display:inline-block;padding:10px 15px;background-color:#336699;color:#ffffff;border-radius:4px;text-decoration:none;font-weight:normal;">
            View Cron Status
        </a>
    </div>
@endsection
