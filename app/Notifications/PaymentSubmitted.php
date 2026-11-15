<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;

/** REQ §12.1 step 5: a payment goes to Accounts for verification. */
class PaymentSubmitted extends AppNotification
{
    public function __construct(public readonly Payment $payment) {}

    protected function title(object $notifiable): string
    {
        return __('Payment to verify');
    }

    protected function body(object $notifiable): string
    {
        return __(':student paid :amount by :method', [
            'student' => $this->payment->student->user->name,
            'amount' => Money::format($this->payment->amount, $this->payment->currency),
            'method' => $this->payment->method->name,
        ]);
    }

    protected function url(object $notifiable): ?string
    {
        return route('accounts.payments.show', $this->payment, absolute: false);
    }

    protected function icon(): string
    {
        return 'pending';
    }

    protected function category(): string
    {
        return 'payment';
    }
}
