<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f3ee;font-family:Arial,Helvetica,sans-serif;color:#191b20">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f3ee;padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e3e0">
            <tr><td style="background:{{ $colour }};padding:20px 28px;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:-0.01em">{{ $site }}</td></tr>
            <tr><td style="padding:28px">
                <p style="margin:0 0 8px;font-size:20px;font-weight:700">{{ $heading }}</p>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#4b5563">{{ __('Hello :name,', ['name' => $booking->name]) }}</p>
                @foreach ($lines as $line)
                    <p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:#4b5563">{{ $line }}</p>
                @endforeach

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;background:#f6f3ee;border-radius:12px;font-size:14px">
                    <tr><td style="padding:12px 16px;color:#6b7280;width:38%">{{ __('Reference') }}</td><td style="padding:12px 16px;font-weight:700">{{ $booking->reference }}</td></tr>
                    <tr><td style="padding:12px 16px;color:#6b7280">{{ __('Type') }}</td><td style="padding:12px 16px">{{ __('Video consultation') }}</td></tr>
                    <tr><td style="padding:12px 16px;color:#6b7280">{{ __('Date') }}</td><td style="padding:12px 16px;font-weight:700">{{ $when }}</td></tr>
                    <tr><td style="padding:12px 16px;color:#6b7280">{{ __('Time') }}</td><td style="padding:12px 16px;font-weight:700">{{ $time }}</td></tr>
                    <tr><td style="padding:12px 16px;color:#6b7280">{{ __('Timezone') }}</td><td style="padding:12px 16px">{{ $booking->timezone }}</td></tr>
                    <tr><td style="padding:12px 16px;color:#6b7280">{{ __('Duration') }}</td><td style="padding:12px 16px">{{ __(':n minutes', ['n' => $booking->duration_minutes]) }}</td></tr>
                    <tr><td style="padding:12px 16px;color:#6b7280">{{ __('Subject') }}</td><td style="padding:12px 16px">{{ $booking->subject }}</td></tr>
                    @if ($previous)
                        <tr><td style="padding:12px 16px;color:#6b7280">{{ __('Previous time') }}</td><td style="padding:12px 16px;text-decoration:line-through;color:#6b7280">{{ $previous }}</td></tr>
                    @endif
                </table>

                @if ($showLink)
                    <p style="margin:0 0 6px;font-size:14px;color:#6b7280">{{ $provider }}</p>
                    <p style="margin:0 0 20px;text-align:center">
                        <a href="{{ $booking->meeting_url }}" style="display:inline-block;background:{{ $colour }};color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 26px;border-radius:12px">{{ __('Join the video consultation') }}</a>
                    </p>
                    <p style="margin:0 0 16px;font-size:13px;color:#6b7280;word-break:break-all">{{ $booking->meeting_url }}</p>
                @endif

                @if ($manageUrl)
                    <p style="margin:0;padding-top:16px;border-top:1px solid #e5e3e0;font-size:13px;line-height:1.55;color:#6b7280">
                        {{ __('Need to cancel or ask for another time?') }}
                        <a href="{{ $manageUrl }}" style="color:{{ $colour }}">{{ __('Manage this appointment') }}</a>
                    </p>
                @endif
            </td></tr>
        </table>
        <p style="margin:16px 0 0;font-size:12px;color:#9ca3af">&copy; {{ date('Y') }} {{ $site }}</p>
    </td></tr>
</table>
</body>
</html>
