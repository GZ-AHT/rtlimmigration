<?php

namespace App\Models;

use App\Enums\CounselorAvailability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Counselor profile attached to a user with the counselor role (REQ §8).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $phone
 * @property string|null $designation
 * @property CounselorAvailability $availability
 * @property int|null $max_active_leads
 * @property string|null $bio
 * @property-read User $user
 */
#[Fillable(['user_id', 'phone', 'designation', 'availability', 'max_active_leads', 'bio'])]
class Counselor extends Model
{
    use LogsActivity;

    protected $attributes = ['availability' => 'available'];

    protected function casts(): array
    {
        return ['availability' => CounselorAvailability::class, 'max_active_leads' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('counselors')->logOnly(['phone', 'designation', 'availability', 'max_active_leads'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
