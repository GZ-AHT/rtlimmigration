<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $from_counselor_id
 * @property int|null $to_counselor_id
 * @property int|null $assigned_by
 * @property string $type
 * @property string|null $note
 * @property CarbonInterface $created_at
 */
#[Fillable(['lead_id', 'from_counselor_id', 'to_counselor_id', 'assigned_by', 'type', 'note'])]
class LeadAssignment extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function fromCounselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_counselor_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function toCounselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_counselor_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }
}
