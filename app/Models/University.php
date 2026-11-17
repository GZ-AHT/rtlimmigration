<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * REQ §15 university profile.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $country_id
 * @property string|null $city
 * @property string|null $address
 * @property string|null $website
 * @property string|null $type
 * @property int|null $established_year
 * @property int|null $ranking
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $admission_requirements
 * @property string|null $scholarships
 * @property int|null $logo_id
 * @property int|null $banner_id
 * @property list<int>|null $gallery
 * @property bool $is_featured
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Country $country
 * @property-read Media|null $logo
 * @property-read Media|null $banner
 * @property-read Collection<int, Course> $courses
 */
#[Fillable(['name', 'slug', 'country_id', 'city', 'address', 'website', 'type', 'established_year', 'ranking', 'summary', 'description', 'admission_requirements', 'scholarships', 'logo_id', 'banner_id', 'gallery', 'is_featured', 'is_active', 'sort_order'])]
class University extends Model
{
    use HasSeo;

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'established_year' => 'integer',
            'ranking' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function banner(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'banner_id');
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** @return list<array{id: int, url: string, alt: string|null, width: int|null, height: int|null, name: string, size: int}> */
    public function galleryMedia(): array
    {
        $ids = $this->gallery ?? [];

        if ($ids === []) {
            return [];
        }

        $media = Media::query()->whereKey($ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(fn (int $id) => $media->get($id)?->present(), $ids)));
    }
}
