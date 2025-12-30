@extends('emails.layout')

@php
    $statusMessage = $status === 'success'
        ? 'All cron automation tasks completed successfully'
        : 'Cron automation failed. Review the details below.';
    $statusBg = $status === 'success' ? '#d4f1ce' : '#fee2e2';
    $statusColor = $status === 'success' ? '#3d841a' : '#b91c1c';
    $chunks = array_chunk($metrics, 3);
@endphp

@section('content')
    <div style="padding:10px 16px;text-align:center;background-color:{{ $statusBg }};color:{{ $statusColor }};font-family:Arial, sans-serif;font-size:14px;">
        {{ $statusMessage }}
    </div>

    <div style="font-family:Arial, sans-serif;color:#475569;margin-top:16px;">
        <div style="text-align:center;font-size:13px;">
            Run started: {{ $startedAt->format($dateFormat . ' H:i') }} ({{ $timeZone }})
            <br>
            Run finished: {{ $finishedAt->format($dateFormat . ' H:i') }} ({{ $timeZone }})
        </div>
    </div>

    @if(!empty($errorMessage))
        <div style="margin-top:16px;padding:12px 14px;background-color:#fef2f2;color:#b91c1c;border:1px solid #fecaca;font-family:Arial, sans-serif;font-size:13px;">
            {{ $errorMessage }}
        </div>
    @endif

    <table cellpadding="0" cellspacing="0" width="100%" style="margin-top:18px;">
        <tbody>
        @foreach($chunks as $row)
            <tr>
                <td style="padding-top:10px;">
                    <table cellpadding="0" cellspacing="0" width="100%">
                        <tbody>
                        <tr>
                            @foreach($row as $index => $metric)
                                <td width="180" height="124" align="center" valign="middle" style="font-family:Arial, sans-serif;color:#555;background:#efefef;">
                                    <table width="90%" cellpadding="0" cellspacing="0">
                                        <tbody>
                                        <tr>
                                            <td align="center" style="text-align:center;font-size:16px;font-weight:600;color:#555;">
                                                {{ $metric['label'] }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="center" style="text-align:center;font-size:42px;color:#111;height:50px;">
                                                {{ $metric['value'] }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="center" style="text-align:center;font-size:13px;font-weight:400;color:#555;">
                                                {{ $metric['subtitle'] }}
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </td>
                                @if($index < count($row) - 1)
                                    <td width="9" style="font-size:1px">&nbsp;</td>
                                @endif
                            @endforeach
                        </tr>
                        </tbody>
                    </table>
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
