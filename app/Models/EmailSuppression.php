<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An address that unsubscribed from bulk email. Bulk sends skip these;
 * one-to-one and system emails (payment receipts, replies) are unaffected.
 *
 * @property int $id
 * @property string $email
 * @property string $reason
 */
#[Fillable(['email', 'reason'])]
class EmailSuppression extends Model
{
    public const UPDATED_AT = null;

    public static function add(string $email, string $reason = 'unsubscribed'): void
    {
        static::query()->firstOrCreate(['email' => strtolower(trim($email))], ['reason' => $reason]);
    }

    /** @return array<string, true> lower-case addresses, for quick lookups */
    public static function lookup(): array
    {
        return array_fill_keys(static::query()->pluck('email')->all(), true);
    }
}
