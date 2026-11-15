<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * REQ §12.2: payment types, created dynamically by Admin.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $default_amount
 * @property bool $generate_on_file_open
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'slug', 'description', 'default_amount', 'generate_on_file_open', 'is_active', 'sort_order'])]
class PaymentType extends Model
{
    protected function casts(): array
    {
        return ['default_amount' => 'integer', 'generate_on_file_open' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
