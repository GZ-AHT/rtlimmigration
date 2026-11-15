<?php

namespace App\Notifications;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;

/**
 * REQ §12.1 step 8 / §27 rule 7: the confirmation or rejection reaches the
 * student and the counselor.
 */
class PaymentDecided extends AppNotification
{
    public function __construct(public readonly Payment $payment) {}

    protected function title(object $notifiable): string
    {
        return $this->payment->status === PaymentStatus::Confirmed ? __('Payment confirmed') : __('Payment not accepted');
    }

    protected function body(object $notifiable): string
    {
        $amount = Money::format($this->payment->amount, $this->payment->currency);
        $invoice = $this->payment->request->invoice_number;

        return $this->payment->status === PaymentStatus::Confirmed
            ? __(':amount for :invoice · receipt :receipt', ['amount' => $amount, 'invoice' => $invoice, 'receipt' => $this->payment->receipt_number])
            : __(':amount for :invoice: :reason', ['amount' => $amount, 'invoice' => $invoice, 'reason' => $this->payment->rejection_reason]);
    }

    protected function url(object $notifiable): ?string
    {
        if ($notifiable instanceof User && $notifiable->is($this->payment->student->user)) {
            return route('portal.payments.index', absolute: false);
        }

        return route('counselor.payment-requests.show', $this->payment->payment_request_id, absolute: false);
    }

    protected function icon(): string
    {
        return $this->payment->status === PaymentStatus::Confirmed ? 'task_alt' : 'block';
    }

    protected function category(): string
    {
        return 'payment';
    }
}
