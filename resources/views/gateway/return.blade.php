@php
    $copy = [
        'paid' => ['Payment received', 'Thank you. '.$amount.' has been received for invoice '.$invoice.'. Your receipt is in your payment history.', '#15803d'],
        'review' => ['Payment being checked', 'We have received your payment of '.$amount.' for invoice '.$invoice.'. Our Accounts team is checking it and will confirm shortly.', '#b45309'],
        'checking' => ['Confirming your payment', 'Your bank has not confirmed the payment to us yet. This usually takes a minute. You do not need to pay again; check your payment history shortly.', '#b45309'],
        'cancelled' => ['Payment cancelled', 'You cancelled the payment, so nothing was charged. You can try again from your payments page.', '#475569'],
        'failed' => ['Payment not completed', 'The payment did not go through and nothing was recorded. Please try again or use another method.', '#b91c1c'],
        'unknown' => ['We could not match this payment', 'Please check your payment history. If money left your account, contact us with your transaction ID.', '#b91c1c'],
    ][$state];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta http-equiv="refresh" content="6;url={{ $next }}">
    <title>{{ $copy[0] }} · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f6f7f9; color: #0f172a; font: 16px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: min(92vw, 440px); background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 32px 28px; text-align: center; }
        h1 { margin: 0 0 10px; font-size: 22px; color: {{ $copy[2] }}; }
        p { margin: 0 0 22px; color: #475569; }
        a { display: inline-block; padding: 11px 20px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; font-weight: 600; }
        small { display: block; margin-top: 14px; color: #94a3b8; }
        @media (prefers-color-scheme: dark) {
            body { background: #0b1220; color: #e2e8f0; }
            main { background: #111a2e; border-color: #1e293b; }
            p { color: #94a3b8; }
            a { background: #e2e8f0; color: #0f172a; }
        }
    </style>
</head>
<body>
    {{-- Easy Checkout shows this inside the gateway's popup frame: take the whole page back to the portal. --}}
    <script>
        if (window.top !== window.self) {
            try { window.top.location.href = @json($next); } catch (e) { /* cross-origin parent: the link below still works */ }
        }
    </script>
    <main>
        <h1>{{ $copy[0] }}</h1>
        <p>{{ $copy[1] }}</p>
        <a href="{{ $next }}">Back to my payments</a>
        <small>You will be taken back automatically in a few seconds.</small>
    </main>
</body>
</html>
