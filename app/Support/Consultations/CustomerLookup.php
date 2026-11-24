<?php

namespace App\Support\Consultations;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Finds what the system already knows about a verified email address, so the
 * booking form can be prefilled and no duplicate customer is created.
 */
class CustomerLookup
{
    /** @return array{name: string|null, phone: string|null, user_id: int|null, lead_id: int|null} */
    public static function forEmail(string $email): array
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->with('student:id,user_id,phone')->first();
        $lead = Lead::query()->where('email_normalized', $email)->latest('id')->first();

        return [
            'name' => $user?->name ?: $lead?->name,
            'phone' => $user?->student?->phone ?: $lead?->phone,
            'user_id' => $user?->id,
            'lead_id' => $lead?->id,
        ];
    }
}
