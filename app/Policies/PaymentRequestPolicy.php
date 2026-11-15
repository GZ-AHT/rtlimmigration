<?php

namespace App\Policies;

use App\Models\PaymentRequest;
use App\Models\Student;
use App\Models\User;

/**
 * REQ §27: counselors see their students' payments; Accounts see all;
 * students see their own.
 */
class PaymentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, PaymentRequest $request): bool
    {
        return StudentPolicy::isOwner($user, $request->student)
            || ($user->can('payments.view') && (PaymentRequest::seesAll($user) || $request->student->counselor_id === $user->id));
    }

    /** REQ §8.1: counselors generate payment requests for their students. */
    public function create(User $user, Student $student): bool
    {
        return $user->can('payments.request') && StudentPolicy::inScope($user, $student);
    }

    public function cancel(User $user, PaymentRequest $request): bool
    {
        return ($user->can('payments.request') && $request->requested_by === $user->id)
            || $user->can('payments.confirm');
    }

    /** Submit a payment against the request: the student, or staff on their behalf. */
    public function pay(User $user, PaymentRequest $request): bool
    {
        return StudentPolicy::isOwner($user, $request->student)
            || ($user->can('payments.request') && StudentPolicy::inScope($user, $request->student))
            || $user->can('payments.confirm');
    }

    /** Accounts recording money received in person (submitted + confirmed at once). */
    public function recordReceived(User $user, PaymentRequest $request): bool
    {
        return $user->can('payments.confirm');
    }
}
