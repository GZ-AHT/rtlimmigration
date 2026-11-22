<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time code sent by email. Only an HMAC of the code is stored; the
 * plain code exists in the email alone.
 *
 * @property int $id
 * @property string $email
 * @property string $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $consumed_at
 * @property string|null $grant_hash
 * @property CarbonInterface|null $grant_expires_at
 * @property CarbonInterface|null $grant_used_at
 */
class OtpCode extends Model
{
    protected $guarded = [];

    protected $hidden = ['code_hash', 'grant_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'grant_expires_at' => 'datetime',
            'grant_used_at' => 'datetime',
        ];
    }
}
