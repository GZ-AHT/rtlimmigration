<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money the office spent (roadmap Q12), for the income & expense reports
 * (REQ §9, §24). Soft-deleted so the record survives for audit.
 *
 * @property int $id
 * @property int $expense_category_id
 * @property string $title
 * @property int $amount
 * @property string $currency
 * @property CarbonInterface $spent_on
 * @property string|null $payee
 * @property string|null $reference
 * @property string|null $attachment_disk
 * @property string|null $attachment_path
 * @property string|null $attachment_original_name
 * @property string|null $attachment_mime_type
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property CarbonInterface|null $created_at
 * @property-read ExpenseCategory $category
 * @property-read User|null $recorder
 */
#[Fillable([
    'expense_category_id', 'title', 'amount', 'currency', 'spent_on', 'payee', 'reference',
    'attachment_disk', 'attachment_path', 'attachment_original_name', 'attachment_mime_type', 'notes', 'recorded_by',
])]
class Expense extends Model
{
    use SoftDeletes;

    protected $attributes = ['currency' => 'BDT'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'spent_on' => 'date'];
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
