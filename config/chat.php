<?php

/*
|--------------------------------------------------------------------------
| Chat security (server-level options)
|--------------------------------------------------------------------------
|
| Limits, keywords, domains, attachment types and CAPTCHA keys are edited
| by Super Admin in Chat security settings. Only the malware scanner is
| configured here, because it depends on what is installed on the server.
|
| CHAT_MALWARE_SCANNER=clamav sends every chat attachment to a running
| ClamAV daemon before it is stored; if the daemon cannot be reached the
| file is refused. "none" relies on the type, content and structure checks.
|
*/

return [

    /*
    | Tawk.to (REQ §18) is switched off: the system's own chat (portal
    | messages and website chat) replaces it. The integration is kept, so
    | TAWK_ENABLED=true brings back the widget, webhook, Chat settings,
    | Chat history and Offline messages screens.
    */
    'tawk' => [
        'enabled' => (bool) env('TAWK_ENABLED', false),
    ],

    'malware' => [
        'driver' => env('CHAT_MALWARE_SCANNER', 'none'),
        'clamav' => [
            // unix:///var/run/clamav/clamd.ctl or tcp://127.0.0.1:3310
            'socket' => env('CLAMAV_SOCKET', 'unix:///var/run/clamav/clamd.ctl'),
            'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
        ],
    ],

];
