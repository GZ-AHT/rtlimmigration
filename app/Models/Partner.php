<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An approved partner (REQ §16 Partner Profile, Verification, Status, Commission).
 *
 * @property int $id
 * @property string $partner_number
 * @property string $organisation
 * @property string $contact_name
 * @property string $email
 * @property string|null $phone
 * @property int|null $country_id
 * @property string|null $city
 * @property string|null $website
 * @property string $type
 * @property string|null $commission_rate
 * @property string $status
 * @property CarbonInterface|null $verified_at
 * @property int|null $verified_by
 * @property string|null $notes
 * @property CarbonInterface|null $created_at
 * @property-read Country|null $country
 */
#[Fillable(['partner_number', 'organisation', 'contact_name', 'email', 'phone', 'country_id', 'city', 'website', 'type', 'commission_rate', 'status', 'verified_at', 'verified_by', 'notes'])]
class Partner extends Model
{
    public const TYPES = ['agency' => 'Agency', 'individual' => 'Individual agent', 'institution' => 'Institution'];

    public const STATUSES = ['active' => 'Active', 'suspended' => 'Suspended'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'commission_rate' => 'decimal:2'];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<PartnerDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocument::class);
    }

    /** @return HasMany<PartnerCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class);
    }

    /** @return HasMany<PartnerPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PartnerPayment::class);
    }
}
