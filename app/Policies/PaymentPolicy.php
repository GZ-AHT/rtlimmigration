<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\User;

/** REQ §9.1 Accounts verify and confirm or reject; §27 rule 5–6. */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return StudentPolicy::isOwner($user, $payment->student)
            || ($user->can('payments.view') && (PaymentRequest::seesAll($user) || $payment->student->counselor_id === $user->id));
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->can('payments.verify');
    }

    public function confirm(User $user, Payment $payment): bool
    {
        return $user->can('payments.confirm');
    }
}
