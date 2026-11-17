{{-- REQ §19.3 SSO: posts a one-time cPanel webmail session to the mail server. No password ever reaches the browser. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>Opening webmail · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f6f7f9; color: #0f172a; font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: min(92vw, 420px); background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 32px 28px; text-align: center; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        p { margin: 0 0 20px; color: #475569; }
        button { padding: 11px 20px; border: 0; border-radius: 10px; background: #0f172a; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        @media (prefers-color-scheme: dark) {
            body { background: #0b1220; color: #e2e8f0; }
            main { background: #111a2e; border-color: #1e293b; }
            p { color: #94a3b8; }
            button { background: #e2e8f0; color: #0f172a; }
        }
    </style>
</head>
<body>
    <main>
        <h1>Opening {{ $email }}</h1>
        <p>Signing you in to webmail…</p>
        <form id="webmail" method="post" action="{{ $action }}">
            <input type="hidden" name="session" value="{{ $session }}">
            <input type="hidden" name="goto_uri" value="{{ $goto }}">
            <button type="submit">Continue to webmail</button>
        </form>
    </main>
    <script>document.getElementById('webmail').submit();</script>
</body>
</html>
