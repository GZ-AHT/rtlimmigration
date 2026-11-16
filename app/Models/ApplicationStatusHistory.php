<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * REQ §13.3: one row per status update.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $from_stage_id
 * @property int|null $to_stage_id
 * @property string|null $from_stage_name
 * @property string $to_stage_name
 * @property string $event
 * @property string|null $note
 * @property int|null $changed_by
 * @property CarbonInterface|null $student_notified_at
 * @property CarbonInterface $created_at
 * @property-read Application $application
 * @property-read User|null $changer
 */
#[Fillable(['application_id', 'from_stage_id', 'to_stage_id', 'from_stage_name', 'to_stage_name', 'event', 'note', 'changed_by', 'student_notified_at'])]
class ApplicationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'student_notified_at' => 'datetime'];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
