<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reset ? __('Password reset code') : __('Verification code') }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f3ee;font-family:Arial,Helvetica,sans-serif;color:#191b20">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ee;padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e3e0">
            <tr><td style="background:{{ $colour }};padding:20px 28px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.01em">{{ $site }}</td></tr>
            <tr><td style="padding:28px">
                <p style="margin:0 0 8px;font-size:18px;font-weight:700">{{ __('Hello :name,', ['name' => $recipientName]) }}</p>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.55;color:#4b5563">
                    @if ($reset)
                        {{ __('Use this code to reset your password.') }}
                    @else
                        {{ __('Use this code to verify your email address and finish setting up your account.') }}
                    @endif
                </p>
                <p style="margin:0 0 20px;text-align:center">
                    <span style="display:inline-block;background:#f6f3ee;border:1px solid #e5e3e0;border-radius:12px;padding:14px 22px;font-size:34px;font-weight:700;letter-spacing:10px;color:{{ $colour }};font-family:'Courier New',monospace">{{ $code }}</span>
                </p>
                <p style="margin:0 0 12px;font-size:14px;line-height:1.55;color:#4b5563;text-align:center">
                    {{ __('This code expires in :minutes minutes and can be used once.', ['minutes' => $minutes]) }}
                </p>
                <p style="margin:0;padding-top:16px;border-top:1px solid #e5e3e0;font-size:13px;line-height:1.55;color:#6b7280">
                    {{ __('Never share this code with anyone. :site staff will never ask for it.', ['site' => $site]) }}
                    @if ($reset)
                        {{ __('If you did not ask to reset your password, you can ignore this email; your password stays the same.') }}
                    @else
                        {{ __('If you did not create this account, you can ignore this email.') }}
                    @endif
                </p>
            </td></tr>
        </table>
        <p style="margin:16px 0 0;font-size:12px;color:#9ca3af">&copy; {{ date('Y') }} {{ $site }}</p>
    </td></tr>
</table>
</body>
</html>
