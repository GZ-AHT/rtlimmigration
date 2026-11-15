<?php

namespace App\Models;

use App\Enums\FileStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A student file / case (REQ §11.2). One student can have several, e.g.
 * one per destination or application (REQ §26).
 *
 * @property int $id
 * @property string $file_number
 * @property int $student_id
 * @property int|null $service_id
 * @property int|null $country_id
 * @property string|null $intake
 * @property FileStatus $status
 * @property string|null $notes
 * @property int|null $opened_by
 * @property CarbonInterface $opened_at
 * @property CarbonInterface|null $closed_at
 * @property string|null $close_reason
 * @property-read Student $student
 * @property-read Service|null $service
 * @property-read Country|null $country
 */
#[Fillable(['file_number', 'student_id', 'service_id', 'country_id', 'intake', 'status', 'notes', 'opened_by', 'opened_at', 'closed_at', 'close_reason'])]
class StudentFile extends Model
{
    use LogsActivity, SoftDeletes;

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['status' => FileStatus::class, 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    /** REQ §11.2 "File status and activity history". */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('files')
            ->logOnly(['service_id', 'country_id', 'intake', 'status', 'close_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by')->withTrashed();
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Files the user may see: those of students in their scope.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('student', fn (Builder $q) => $q->visibleTo($user));
    }
}
