<?php

namespace App\Console\Commands;

use App\Actions\Payments\PaymentLedger;
use App\Enums\PaymentRequestStatus;
use App\Models\PaymentRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Moves unpaid / partly paid requests past their due date to "Overdue" (REQ §12.4 "Due"). */
#[Signature('payments:mark-overdue')]
#[Description('Recalculate payment requests whose due date has passed')]
class MarkOverduePayments extends Command
{
    public function handle(PaymentLedger $ledger): int
    {
        $count = 0;

        PaymentRequest::query()
            ->whereIn('status', [PaymentRequestStatus::Unpaid->value, PaymentRequestStatus::Partial->value, PaymentRequestStatus::Rejected->value])
            ->whereDate('due_date', '<', today())
            ->chunkById(200, function ($requests) use ($ledger, &$count) {
                foreach ($requests as $request) {
                    $ledger->recalculate($request);
                    $count++;
                }
            });

        $this->info("Checked {$count} overdue request(s).");

        return self::SUCCESS;
    }
}
