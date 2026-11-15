<?php

/*
|--------------------------------------------------------------------------
| Student documents (REQ §10 "Documents Management", §23.4)
|--------------------------------------------------------------------------
|
| Files are stored on a private disk and only served through an authorised
| download route. Upload limits move to System Settings in Phase 11.
|
*/

return [

    'disk' => env('DOCUMENTS_DISK', 'local'),

    'directory' => 'student-documents',

    'max_size_kb' => (int) env('DOCUMENTS_MAX_KB', 10240),

    // Extension => MIME types accepted for it (checked against file contents).
    'allowed' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ],

    // Types the browser may preview inline; everything else downloads.
    'inline' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],

];
