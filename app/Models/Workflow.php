<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An ordered list of stages (REQ §13: "configurable by Super Admin because
 * different countries and visa types may have different workflows").
 *
 * @property int $id
 * @property string $name
 * @property int|null $visa_type_id
 * @property int|null $country_id
 * @property bool $is_active
 * @property-read VisaType|null $visaType
 * @property-read Country|null $country
 */
#[Fillable(['name', 'visa_type_id', 'country_id', 'is_active'])]
class Workflow extends Model
{
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<WorkflowStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<VisaType, $this> */
    public function visaType(): BelongsTo
    {
        return $this->belongsTo(VisaType::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function isDefault(): bool
    {
        return $this->visa_type_id === null && $this->country_id === null;
    }

    /**
     * The workflow a new application follows. Most specific first: visa
     * type and country, then visa type, then country, then the default.
     */
    public static function resolve(int $visaTypeId, ?int $countryId): ?self
    {
        $candidates = self::query()
            ->where('is_active', true)
            ->whereHas('stages')
            ->where(fn ($q) => $q->whereNull('visa_type_id')->orWhere('visa_type_id', $visaTypeId))
            ->where(fn ($q) => $q->whereNull('country_id')->when($countryId, fn ($q) => $q->orWhere('country_id', $countryId)))
            ->get();

        return $candidates
            ->sortByDesc(fn (self $w) => ($w->visa_type_id !== null ? 2 : 0) + ($w->country_id !== null ? 1 : 0))
            ->first();
    }
}
