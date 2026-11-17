<?php

/*
|--------------------------------------------------------------------------
| Human-readable document numbers (REQ §11.2, §12.5)
|--------------------------------------------------------------------------
|
| Placeholders: {prefix}, {Y} (4-digit year), {y} (2-digit), {m}, {seq}.
| "reset" decides when the counter restarts: "never", "yearly" or "monthly".
| "pad" left-pads {seq} with zeros.
|
*/

return [

    'types' => [
        'student' => ['prefix' => 'STU', 'format' => '{prefix}-{Y}-{seq}', 'pad' => 5, 'reset' => 'yearly'],
        'partner' => ['prefix' => 'PTR', 'format' => '{prefix}-{seq}', 'pad' => 4, 'reset' => 'never'],
        'file' => ['prefix' => 'FILE', 'format' => '{prefix}-{Y}-{seq}', 'pad' => 5, 'reset' => 'yearly'],
        'invoice' => ['prefix' => 'INV', 'format' => '{prefix}-{Y}{m}-{seq}', 'pad' => 5, 'reset' => 'monthly'],
        'receipt' => ['prefix' => 'RCP', 'format' => '{prefix}-{Y}{m}-{seq}', 'pad' => 5, 'reset' => 'monthly'],
        'application' => ['prefix' => 'APP', 'format' => '{prefix}-{Y}-{seq}', 'pad' => 5, 'reset' => 'yearly'],
        'refund' => ['prefix' => 'RFD', 'format' => '{prefix}-{Y}-{seq}', 'pad' => 5, 'reset' => 'yearly'],
        'ticket' => ['prefix' => 'TKT', 'format' => '{prefix}-{seq}', 'pad' => 6, 'reset' => 'never'],
        'lead' => ['prefix' => 'LD', 'format' => '{prefix}-{Y}-{seq}', 'pad' => 5, 'reset' => 'yearly'],
    ],

];
