<?php

namespace App\Listeners;

use App\Actions\Payments\PaymentLedger;
use App\Events\StudentFileOpened;
use App\Models\PaymentType;
use App\Notifications\FileOpeningFeeNeeded;

/**
 * REQ §11 step 6: when a file is opened, raise the file-opening fee. Uses
 * the payment type(s) flagged "generate on file open" with a default
 * amount; if none has an amount, the counselor is asked to raise it.
 */
class RaiseFileOpeningFee
{
    public function __construct(private PaymentLedger $ledger) {}

    public function handle(StudentFileOpened $event): void
    {
        $types = PaymentType::active()->where('generate_on_file_open', true)->get();

        foreach ($types as $type) {
            if ($type->default_amount) {
                $this->ledger->createRequest(
                    $event->file->student,
                    $type,
                    $type->default_amount,
                    now()->addDays((int) config('payments.default_due_days', 7)),
                    __('For file :number', ['number' => $event->file->file_number]),
                    $event->file,
                    $event->openedBy,
                );

                continue;
            }

            $counselor = $event->file->student->counselor ?? $event->openedBy;
            $counselor?->notify(new FileOpeningFeeNeeded($event->file));
        }
    }
}
