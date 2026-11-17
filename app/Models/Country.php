<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $iso2
 * @property bool $is_destination
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'iso2', 'is_destination', 'is_active', 'sort_order'])]
class Country extends Model
{
    protected function casts(): array
    {
        return ['is_destination' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @param Builder<self> $query */
    public function scopeDestinations(Builder $query): void
    {
        $query->where('is_destination', true)->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasMany<University, $this> */
    public function universities(): HasMany
    {
        return $this->hasMany(University::class);
    }
}
