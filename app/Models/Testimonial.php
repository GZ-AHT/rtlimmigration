<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student story (REQ §21.7 Testimonials, §21.8 Student Testimonials).
 *
 * @property int $id
 * @property string $name
 * @property string|null $role
 * @property int|null $country_id
 * @property int|null $photo_id
 * @property string $quote
 * @property int $rating
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 * @property-read Media|null $photo
 * @property-read Country|null $country
 */
#[Fillable(['name', 'role', 'country_id', 'photo_id', 'quote', 'rating', 'is_active', 'is_featured', 'sort_order'])]
class Testimonial extends Model
{
    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_active' => 'boolean', 'is_featured' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Media, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_id');
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
