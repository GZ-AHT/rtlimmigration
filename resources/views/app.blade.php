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

            html.website {
                background-color: #ffffff;
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
        @if ($isWebsite && $siteCode && ($page['props']['seo']['enabled'] ?? true))
            {{-- REQ §22.1: search-engine verification, analytics and structured data. IDs are format-checked when saved. --}}
            @if ($v = $siteCode->get('seo.google_verification'))
                <meta name="google-site-verification" content="{{ $v }}">
            @endif
            @if ($v = $siteCode->get('seo.bing_verification'))
                <meta name="msvalidate.01" content="{{ $v }}">
            @endif
            @if (($gtm = $siteCode->get('seo.gtm_id')) && preg_match('/^GTM-[A-Z0-9]+$/', $gtm))
                <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtm }}');</script>
            @endif
            @if (($ga = $siteCode->get('seo.ga_id')) && preg_match('/^G-[A-Z0-9]+$/', $ga))
                <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
                <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $ga }}');</script>
            @endif
            @foreach (($page['props']['seo']['schema'] ?? []) as $node)
                <script type="application/ld+json">{!! json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
            @endforeach
        @endif
        @if ($siteCode)
            @if (($fb = $siteCode->get('integrations.fb_pixel_id')) && preg_match('/^\d{8,20}$/', $fb))
                <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $fb }}');fbq('track','PageView');</script>
            @endif
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
