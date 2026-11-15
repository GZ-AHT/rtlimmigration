<?php

namespace App\Models;

use App\Enums\PaymentMethodKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * REQ §12.3 payment methods.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property PaymentMethodKind $kind
 * @property string|null $instructions
 * @property bool $requires_reference
 * @property bool $requires_evidence
 * @property bool $student_selectable
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'slug', 'kind', 'instructions', 'requires_reference', 'requires_evidence', 'student_selectable', 'is_active', 'sort_order'])]
class PaymentMethod extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => PaymentMethodKind::class,
            'requires_reference' => 'boolean',
            'requires_evidence' => 'boolean',
            'student_selectable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
