<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A link found by the scheduled scan that did not load (REQ §22.3).
 *
 * @property int $id
 * @property string $source_type
 * @property int|null $source_id
 * @property string $source_label
 * @property string|null $source_url
 * @property string $target_url
 * @property int|null $status_code
 * @property string|null $error
 * @property Carbon $checked_at
 */
#[Fillable(['source_type', 'source_id', 'source_label', 'source_url', 'target_url', 'status_code', 'error', 'checked_at'])]
class BrokenLink extends Model
{
    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'status_code' => 'integer'];
    }
}
