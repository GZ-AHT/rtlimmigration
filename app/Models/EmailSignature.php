<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A staff member's signature, added under mail they send from this system
 * (REQ §3 "Email Signature"). Roundcube keeps its own signatures.
 *
 * @property int $id
 * @property int $user_id
 * @property string $body
 */
#[Fillable(['user_id', 'body'])]
class EmailSignature extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
