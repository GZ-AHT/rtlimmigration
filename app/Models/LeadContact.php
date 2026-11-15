<?php

namespace App\Models;

use App\Enums\ContactChannel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lead_id
 * @property int|null $user_id
 * @property ContactChannel $channel
 * @property string $summary
 * @property CarbonInterface $contacted_at
 */
#[Fillable(['lead_id', 'user_id', 'channel', 'summary', 'contacted_at'])]
class LeadContact extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['channel' => ContactChannel::class, 'contacted_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
