<?php

namespace App\Models;

use App\Enums\VisaKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of application the office handles (roadmap Q14): its
 * requirements, document checklist and fee (REQ §17).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property VisaKind $kind
 * @property string|null $description
 * @property int|null $payment_type_id
 * @property int|null $fee_amount
 * @property bool $is_active
 * @property int $sort_order
 * @property-read PaymentType|null $paymentType
 */
#[Fillable(['name', 'slug', 'kind', 'description', 'payment_type_id', 'fee_amount', 'is_active', 'sort_order'])]
class VisaType extends Model
{
    protected $attributes = ['kind' => 'other', 'is_active' => true];

    protected function casts(): array
    {
        return ['kind' => VisaKind::class, 'fee_amount' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<VisaRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(VisaRequirement::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsToMany<DocumentCategory, $this> */
    public function documentCategories(): BelongsToMany
    {
        return $this->belongsToMany(DocumentCategory::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @return BelongsTo<PaymentType, $this> */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
