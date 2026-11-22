{{-- Unsubscribe from bulk email. GET shows the button; POST (this form, or a mail app's one-click) does it. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>{{ $done ? 'Unsubscribed' : 'Unsubscribe' }} · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f6f7f9; color: #0f172a; font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: min(92vw, 440px); background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 32px 28px; text-align: center; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        p { margin: 0 0 20px; color: #475569; }
        b { color: #0f172a; word-break: break-all; }
        button { padding: 12px 22px; min-height: 44px; border: 0; border-radius: 10px; background: #d71920; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        @media (prefers-color-scheme: dark) {
            body { background: #0b1220; color: #e2e8f0; }
            main { background: #111a2e; border-color: #1e293b; }
            p { color: #94a3b8; } b { color: #e2e8f0; }
        }
    </style>
</head>
<body>
<main>
    @if ($done)
        <h1>You are unsubscribed</h1>
        <p><b>{{ $email }}</b> will no longer receive bulk email from {{ config('app.name') }}. You may still get messages about your own application, such as payment receipts and replies to your questions.</p>
    @else
        <h1>Unsubscribe from bulk email?</h1>
        <p>Stop news and updates from {{ config('app.name') }} going to <b>{{ $email }}</b>. Messages about your own application will still reach you.</p>
        <form method="post" action="{{ request()->fullUrl() }}">
            @csrf
            <button type="submit">Unsubscribe</button>
        </form>
    @endif
</main>
</body>
</html>
