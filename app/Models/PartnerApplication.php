<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * REQ §16 partner application from the website (pending → approved / rejected).
 *
 * @property int $id
 * @property string $organisation
 * @property string $contact_name
 * @property string $email
 * @property string|null $phone
 * @property int|null $country_id
 * @property string|null $city
 * @property string|null $website
 * @property string $type
 * @property int|null $students_per_year
 * @property string|null $message
 * @property string $status
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property string|null $review_note
 * @property int|null $partner_id
 * @property string|null $ip_address
 * @property CarbonInterface|null $created_at
 * @property-read Country|null $country
 * @property-read User|null $reviewer
 * @property-read Partner|null $partner
 */
#[Fillable(['organisation', 'contact_name', 'email', 'phone', 'country_id', 'city', 'website', 'type', 'students_per_year', 'message', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'partner_id', 'ip_address'])]
class PartnerApplication extends Model
{
    public const STATUSES = ['pending' => ['Pending', 'warning'], 'approved' => ['Approved', 'success'], 'rejected' => ['Rejected', 'danger']];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'students_per_year' => 'integer'];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return HasMany<PartnerDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(PartnerDocument::class);
    }
}
