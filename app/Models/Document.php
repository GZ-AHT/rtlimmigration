<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A student document on the private disk (REQ §10, §11.2).
 *
 * @property int $id
 * @property int $student_id
 * @property int|null $student_file_id
 * @property int|null $category_id
 * @property string|null $title
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property DocumentStatus $status
 * @property int|null $uploaded_by
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property string|null $rejection_reason
 * @property int|null $replaces_id
 * @property bool $is_current
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property-read Student $student
 * @property-read DocumentCategory|null $category
 * @property-read StudentFile|null $file
 * @property-read User|null $uploader
 * @property-read User|null $reviewer
 */
#[Fillable([
    'student_id', 'student_file_id', 'category_id', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size',
    'status', 'uploaded_by', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'replaces_id', 'is_current', 'expires_at',
])]
class Document extends Model
{
    use LogsActivity, SoftDeletes;

    protected $attributes = ['status' => 'pending', 'is_current' => true];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'reviewed_at' => 'datetime',
            'is_current' => 'boolean',
            'expires_at' => 'date',
            'size' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('documents')
            ->logOnly(['status', 'rejection_reason', 'category_id', 'student_file_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<DocumentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    /** @return BelongsTo<StudentFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StudentFile::class, 'student_file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /** @return BelongsTo<Document, $this> */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'replaces_id')->withTrashed();
    }

    public function displayName(): string
    {
        return $this->title ?: ($this->category->name ?? $this->original_name);
    }
}
