<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An IP address that may not send chat messages or start website chats,
 * set by a Super Admin or automatically after repeated abuse.
 *
 * @property int $id
 * @property string $ip_address
 * @property string $reason
 * @property bool $automatic
 * @property CarbonInterface|null $expires_at
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property-read User|null $creator
 */
#[Fillable(['ip_address', 'reason', 'automatic', 'expires_at', 'created_by'])]
class ChatIpBlock extends Model
{
    protected function casts(): array
    {
        return ['automatic' => 'boolean', 'expires_at' => 'datetime'];
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
