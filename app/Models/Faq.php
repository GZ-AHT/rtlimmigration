<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ §21.8 FAQ page and §21.7 homepage FAQ section. The answer is plain text.
 *
 * @property int $id
 * @property int|null $faq_category_id
 * @property string $question
 * @property string $answer
 * @property bool $is_active
 * @property bool $show_on_home
 * @property int $sort_order
 * @property-read FaqCategory|null $category
 */
#[Fillable(['faq_category_id', 'question', 'answer', 'is_active', 'show_on_home', 'sort_order'])]
class Faq extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_on_home' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<FaqCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }
}
