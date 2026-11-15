<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $user_id
 * @property CarbonInterface $due_at
 * @property string|null $note
 * @property CarbonInterface|null $completed_at
 * @property int|null $completed_by
 * @property string|null $outcome
 * @property CarbonInterface|null $reminded_at
 * @property-read Lead $lead
 * @property-read User|null $user
 */
#[Fillable(['lead_id', 'user_id', 'due_at', 'note', 'completed_at', 'completed_by', 'outcome', 'reminded_at'])]
class LeadFollowUp extends Model
{
    protected $table = 'lead_followups';

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
