<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasSeo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A website page built from sections (REQ §5, roadmap Q7). Each section is
 * {id, type, enabled, data, settings}; types and their fields are defined
 * in App\Support\Cms\SectionTypes and cleaned by SectionSanitizer.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $system_key
 * @property string|null $category
 * @property PublishStatus $status
 * @property CarbonInterface|null $published_at
 * @property string $visibility
 * @property list<array{id: string, type: string, enabled: bool, data: array<string, mixed>, settings: array<string, mixed>}> $sections
 * @property string|null $custom_css
 * @property string|null $custom_js
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['title', 'slug', 'system_key', 'category', 'status', 'published_at', 'visibility', 'sections', 'custom_css', 'custom_js', 'created_by', 'updated_by'])]
class Page extends Model
{
    use HasSeo, SoftDeletes;

    public const VISIBILITY = ['public' => 'Everyone', 'members' => 'Signed-in users only', 'hidden' => 'Hidden (direct link only)'];

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'sections' => 'array',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', PublishStatus::Published)->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isLive(): bool
    {
        return $this->status === PublishStatus::Published && ($this->published_at === null || ! $this->published_at->isFuture());
    }

    /** @return HasMany<PageRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }
}
