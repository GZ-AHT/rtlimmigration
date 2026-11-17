<?php

namespace App\Support\Email;

use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Which mailboxes a user may send from inside this system. */
class SenderAccounts
{
    /** @return Collection<int, EmailAccount> */
    public static function for(User $user): Collection
    {
        $query = EmailAccount::query()->where('status', 'active')->whereNotNull('password')->orderBy('email');

        if (! $user->can('email.manage')) {
            $query->whereHas('users', fn ($q) => $q->whereKey($user->id)->where('email_account_user.can_send', true));
        }

        return $query->get();
    }

    public static function allows(User $user, ?int $accountId): bool
    {
        return $accountId === null || self::for($user)->contains('id', $accountId);
    }

    /** @return list<array{value: string, label: string}> the default mailer first, as "" */
    public static function options(User $user): array
    {
        return [
            ['value' => '', 'label' => __('System address (:email)', ['email' => config('mail.from.address')])],
            ...self::for($user)->map(fn (EmailAccount $a) => ['value' => (string) $a->id, 'label' => $a->display_name ? "{$a->display_name} <{$a->email}>" : $a->email])->values()->all(),
        ];
    }
}
