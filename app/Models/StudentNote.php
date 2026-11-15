<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff-only note about a student (REQ §10 "Student Notes").
 *
 * @property int $id
 * @property int $student_id
 * @property int|null $user_id
 * @property string $body
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['student_id', 'user_id', 'body'])]
class StudentNote extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
