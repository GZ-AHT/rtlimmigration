<?php

/*
|--------------------------------------------------------------------------
| Payments (REQ §12)
|--------------------------------------------------------------------------
|
| Currency and defaults until System Settings (Phase 11) makes them
| editable. Gateway credentials arrive with Phase 5 and are never sent to
| the browser.
|
*/

return [

    'currency' => env('PAYMENTS_CURRENCY', 'BDT'),

    // Due date for automatically raised requests (file-opening fee).
    'default_due_days' => (int) env('PAYMENTS_DEFAULT_DUE_DAYS', 7),

    // Proof-of-payment uploads (deposit slips, screenshots).
    'evidence_max_kb' => (int) env('PAYMENTS_EVIDENCE_MAX_KB', 5120),
    'evidence_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

];
