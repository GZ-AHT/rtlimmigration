<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;

/**
 * REQ §27 rule 10: refunds require authorised approval. Counselors and
 * students may ask; only refunds.approve may approve; Accounts pays out.
 */
class RefundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view') || $user->can('refunds.request') || $user->can('refunds.approve') || $user->can('refunds.process');
    }

    public function view(User $user, Refund $refund): bool
    {
        return StudentPolicy::isOwner($user, $refund->student)
            || ($this->viewAny($user) && (Refund::seesAll($user) || $refund->student->counselor_id === $user->id));
    }

    /** Ask for a refund of one payment. */
    public function create(User $user, Payment $payment): bool
    {
        return StudentPolicy::isOwner($user, $payment->student)
            || ($user->can('refunds.request') && StudentPolicy::inScope($user, $payment->student))
            || $user->can('refunds.process');
    }

    public function approve(User $user, Refund $refund): bool
    {
        return $user->can('refunds.approve');
    }

    public function process(User $user, Refund $refund): bool
    {
        return $user->can('refunds.process');
    }
}
