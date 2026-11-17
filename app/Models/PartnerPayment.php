<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money paid to a partner (REQ §16 Partner Payment History).
 *
 * @property int $id
 * @property int $partner_id
 * @property string $amount
 * @property string $currency
 * @property string $method
 * @property string|null $reference
 * @property CarbonInterface $paid_on
 * @property string|null $note
 * @property int|null $recorded_by
 * @property-read User|null $recorder
 */
#[Fillable(['partner_id', 'amount', 'currency', 'method', 'reference', 'paid_on', 'note', 'recorded_by'])]
class PartnerPayment extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
