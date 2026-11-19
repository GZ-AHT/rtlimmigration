@php
    // Public website and auth pages are light-only (roadmap Q9, Q11) and carry the
    // Super Admin's site-wide custom code (Q16); admin and portal pages don't.
    $isWebsite = str_starts_with($page['component'] ?? '', 'website/') || str_starts_with($page['component'] ?? '', 'auth/');
    $siteCode = $isWebsite ? app(\App\Support\Settings\SettingsRepository::class) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ! $isWebsite && ($appearance ?? 'system') == 'dark', 'website' => $isWebsite])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $isWebsite ? "light" : ($appearance ?? "system") }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f7f8fa;
            }

            html.website,
            html.website body {
                background-color: #ffffff;
                overflow-x: hidden;
            }

            html.dark {
                background-color: #111318;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
        @if ($siteCode)
            @if ($css = $siteCode->get('code.css'))
                <style>{!! str_ireplace('</style', '', $css) !!}</style>
            @endif
            {!! $siteCode->get('code.head_js') !!}
        @endif
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
        @if ($siteCode)
            {!! $siteCode->get('code.body_js') !!}
        @endif
    </body>
</html>
