<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reply or internal note on a ticket. Internal notes are staff-only and
 * never emailed (REQ §7 "Internal Notes", "Reply via Email").
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $is_internal
 * @property CarbonInterface|null $emailed_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['ticket_id', 'user_id', 'body', 'is_internal', 'emailed_at'])]
class TicketReply extends Model
{
    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'emailed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
