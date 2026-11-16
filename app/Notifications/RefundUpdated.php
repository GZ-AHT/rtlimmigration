<?php

namespace App\Notifications;

use App\Models\Refund;
use App\Models\User;
use App\Support\Money;

/**
 * REQ §9.1 refunds: tells approvers a refund is waiting, Accounts that one
 * is ready to pay out, and the student and counselor how it ended.
 */
class RefundUpdated extends AppNotification
{
    /** @param  'requested'|'approved'|'rejected'|'processed'  $event */
    public function __construct(public readonly Refund $refund, public readonly string $event) {}

    protected function title(object $notifiable): string
    {
        return match ($this->event) {
            'requested' => __('Refund awaiting approval'),
            'approved' => __('Refund approved'),
            'rejected' => __('Refund not approved'),
            default => __('Refund paid'),
        };
    }

    protected function body(object $notifiable): string
    {
        $amount = Money::format($this->refund->amount, $this->refund->currency);
        $base = __(':amount · :number · :invoice', ['amount' => $amount, 'number' => $this->refund->refund_number, 'invoice' => $this->refund->request->invoice_number]);

        return $this->event === 'rejected' && filled($this->refund->decision_note)
            ? $base.': '.$this->refund->decision_note
            : $base;
    }

    protected function url(object $notifiable): ?string
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        if ($notifiable->is($this->refund->student->user)) {
            return route('portal.refunds.index', absolute: false);
        }

        $area = $notifiable->homeArea()?->value;

        return in_array($area, ['admin', 'accounts', 'counselor'], true)
            ? route("{$area}.refunds.show", $this->refund->id, absolute: false)
            : null;
    }

    protected function icon(): string
    {
        return $this->event === 'rejected' ? 'block' : 'currency_exchange';
    }

    protected function category(): string
    {
        return 'payment';
    }
}
