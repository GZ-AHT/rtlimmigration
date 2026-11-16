<?php

/*
|--------------------------------------------------------------------------
| Payments (REQ §12)
|--------------------------------------------------------------------------
|
| Currency and defaults until System Settings (Phase 11) makes them
| editable. Gateway credentials are read from the environment and are
| never sent to the browser.
|
*/

return [

    'currency' => env('PAYMENTS_CURRENCY', 'BDT'),

    // Due date for automatically raised requests (file-opening fee).
    'default_due_days' => (int) env('PAYMENTS_DEFAULT_DUE_DAYS', 7),

    // Proof-of-payment uploads (deposit slips, screenshots).
    'evidence_max_kb' => (int) env('PAYMENTS_EVIDENCE_MAX_KB', 5120),
    'evidence_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

    /*
    | Online gateway (REQ §12.3, roadmap Q3). Leave PAYMENT_GATEWAY empty to
    | switch online payment off; students then only see the manual methods.
    | "method_slug" is the payment method that gateway payments are filed
    | under (seeded by ReferenceDataSeeder).
    */
    'gateway' => env('PAYMENT_GATEWAY'),

    'gateways' => [
        'sslcommerz' => [
            'store_id' => env('SSLCOMMERZ_STORE_ID'),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
            'sandbox' => (bool) env('SSLCOMMERZ_SANDBOX', true),
            // "easy": SSLCommerz Easy Checkout popup on our page.
            // "hosted": send the browser to SSLCommerz's own page.
            'checkout' => env('SSLCOMMERZ_CHECKOUT', 'easy'),
            'method_slug' => 'online-payment-sslcommerz',
        ],
    ],

    // Attachments on expense records (bills, vouchers).
    'expense_attachment_max_kb' => (int) env('PAYMENTS_EXPENSE_ATTACHMENT_MAX_KB', 5120),

];
