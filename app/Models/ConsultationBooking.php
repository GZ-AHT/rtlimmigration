<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A video consultation a visitor booked. Instants are UTC; `timezone` is the
 * visitor's own zone, used to word their emails.
 *
 * @property int $id
 * @property string $reference
 * @property string $name
 * @property string $email
 * @property string $phone E.164
 * @property string $subject
 * @property string|null $message
 * @property int|null $user_id
 * @property int|null $lead_id
 * @property int|null $counselor_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $duration_minutes
 * @property string $timezone
 * @property ConsultationStatus $status
 * @property CarbonInterface|null $active_slot
 * @property string|null $meeting_provider google_meet, zoom or other
 * @property string|null $meeting_url
 * @property bool $meeting_is_default the link is the saved default, not one entered for this booking
 * @property CarbonInterface|null $meeting_sent_at
 * @property CarbonInterface|null $reminder_sent_at
 * @property CarbonInterface|null $confirmed_at
 * @property CarbonInterface|null $cancelled_at
 * @property-read Collection<int, ConsultationBookingEvent> $events
 * @property-read User|null $counselor
 */
#[Fillable(['reference', 'name', 'email', 'phone', 'subject', 'message', 'user_id', 'lead_id', 'counselor_id', 'starts_at', 'ends_at', 'duration_minutes', 'timezone', 'status', 'active_slot', 'meeting_provider', 'meeting_url', 'meeting_is_default', 'meeting_sent_at', 'reminder_sent_at', 'confirmed_at', 'cancelled_at'])]
class ConsultationBooking extends Model
{
    public const PROVIDERS = ['google_meet' => 'Google Meet', 'zoom' => 'Zoom', 'other' => 'Custom link'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => ConsultationStatus::class,
            'active_slot' => 'datetime',
            'meeting_is_default' => 'boolean',
            'meeting_sent_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<ConsultationBookingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ConsultationBookingEvent::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public static function newReference(): string
    {
        do {
            $reference = 'CB-'.strtoupper(Str::random(6));
        } while (self::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /** The visitor's local time, e.g. "Tue 3 Dec 2026, 4:00 PM (Asia/Dhaka)". */
    public function localTime(): string
    {
        return $this->starts_at->setTimezone($this->timezone)->format('D j M Y, g:i A').' ('.$this->timezone.')';
    }
}
