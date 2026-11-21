<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A backup file in storage/app/backups (REQ §23.5).
 *
 * @property int $id
 * @property string $type
 * @property string $trigger
 * @property string $filename
 * @property int $size
 * @property string $status
 * @property string|null $error
 * @property int|null $created_by
 * @property CarbonInterface|null $restored_at
 * @property CarbonInterface|null $created_at
 * @property-read User|null $creator
 */
#[Fillable(['type', 'trigger', 'filename', 'size', 'status', 'error', 'created_by', 'restored_at'])]
class Backup extends Model
{
    protected function casts(): array
    {
        return ['size' => 'integer', 'restored_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
