<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $visa_type_id
 * @property int|null $country_id
 * @property string $title
 * @property string|null $description
 * @property int $sort_order
 * @property-read Country|null $country
 */
#[Fillable(['visa_type_id', 'country_id', 'title', 'description', 'sort_order'])]
class VisaRequirement extends Model
{
    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
