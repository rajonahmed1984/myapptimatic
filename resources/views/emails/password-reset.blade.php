@extends('emails.layout')

@section('content')
    <div style="font-family:Arial,sans-serif;color:#334155;">
        <div style="padding:18px 20px;margin-bottom:22px;border:1px solid #dbeafe;background:#eff6ff;border-radius:10px;">
            <div style="font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;color:#0284c7;">Account security</div>
            <div style="margin-top:6px;font-size:22px;line-height:1.3;font-weight:bold;color:#0f172a;">Reset your password</div>
        </div>

        <p style="margin:0 0 14px;">Hello {{ $userName }},</p>
        <p style="margin:0 0 20px;">We received a request to reset the password for your {{ $companyName }} account.</p>

        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:0 auto 22px;">
            <tr>
                <td align="center" bgcolor="#0d9488" style="border-radius:999px;">
                    <a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 26px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">Reset Password</a>
                </td>
            </tr>
        </table>

        <div style="padding:14px 16px;border-left:4px solid #f59e0b;background:#fffbeb;color:#78350f;font-size:13px;line-height:1.6;">
            This secure link expires in {{ $expiresInMinutes }} minutes. If you did not request a password reset, you can safely ignore this email.
        </div>

        <p style="margin:22px 0 8px;font-size:12px;color:#64748b;">If the button does not work, copy and paste this link into your browser:</p>
        <p style="margin:0;word-break:break-all;font-size:12px;line-height:1.5;"><a href="{{ $resetUrl }}" style="color:#0f766e;">{{ $resetUrl }}</a></p>
    </div>
@endsection
