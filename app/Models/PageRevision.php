<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved copy of a page's content before each change (REQ §5 Page Revision History).
 *
 * @property int $id
 * @property int $page_id
 * @property string $title
 * @property list<array<string, mixed>> $sections
 * @property string|null $custom_css
 * @property string|null $custom_js
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property-read User|null $author
 */
#[WithoutTimestamps]
#[Fillable(['page_id', 'title', 'sections', 'custom_css', 'custom_js', 'created_by', 'created_at'])]
class PageRevision extends Model
{
    protected function casts(): array
    {
        return ['sections' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
