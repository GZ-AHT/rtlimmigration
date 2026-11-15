<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use App\Support\Money;

/** REQ §12.1 step 3: the student receives the request in their dashboard. */
class PaymentRequested extends AppNotification
{
    public function __construct(public readonly PaymentRequest $request) {}

    protected function title(object $notifiable): string
    {
        return __('New payment request');
    }

    protected function body(object $notifiable): string
    {
        return __(':type · :amount', ['type' => $this->request->type->name, 'amount' => Money::format($this->request->amount, $this->request->currency)])
            .($this->request->due_date ? ' · '.__('due :date', ['date' => $this->request->due_date->format('d M Y')]) : '');
    }

    protected function url(object $notifiable): ?string
    {
        return route('portal.payments.index', absolute: false);
    }

    protected function icon(): string
    {
        return 'request_quote';
    }

    protected function category(): string
    {
        return 'payment';
    }
}
