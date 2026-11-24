<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a booking's history: created, approved, rescheduled, cancelled,
 * meeting link, emails sent, or an internal note (type "note").
 *
 * @property int $id
 * @property int $consultation_booking_id
 * @property int|null $user_id
 * @property string $type
 * @property string|null $note
 * @property array<string, mixed>|null $data
 * @property CarbonInterface|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['consultation_booking_id', 'user_id', 'type', 'note', 'data'])]
class ConsultationBookingEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
