<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commission owed to a partner for a referral (REQ §16 Partner Commission).
 *
 * @property int $id
 * @property int $partner_id
 * @property int|null $student_id
 * @property string $description
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property-read Student|null $student
 */
#[Fillable(['partner_id', 'student_id', 'description', 'amount', 'currency', 'status', 'created_by'])]
class PartnerCommission extends Model
{
    public const STATUSES = ['pending' => ['Pending', 'warning'], 'approved' => ['Approved', 'info'], 'paid' => ['Paid', 'success'], 'cancelled' => ['Cancelled', 'neutral']];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
