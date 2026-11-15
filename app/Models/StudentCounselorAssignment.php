<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int|null $counselor_id
 * @property int|null $assigned_by
 * @property string|null $note
 * @property CarbonInterface $assigned_at
 * @property CarbonInterface|null $ended_at
 */
#[Fillable(['student_id', 'counselor_id', 'assigned_by', 'note', 'assigned_at', 'ended_at'])]
class StudentCounselorAssignment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }
}
