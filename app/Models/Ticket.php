<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Support ticket (REQ §7).
 *
 * @property int $id
 * @property string $ticket_number
 * @property string $subject
 * @property string $description
 * @property int|null $category_id
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property int|null $requester_id
 * @property string|null $requester_name
 * @property string|null $requester_email
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property CarbonInterface|null $last_reply_at
 * @property CarbonInterface|null $resolved_at
 * @property CarbonInterface|null $closed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User|null $requester
 * @property-read User|null $assignee
 * @property-read TicketCategory|null $category
 */
#[Fillable([
    'ticket_number', 'subject', 'description', 'category_id', 'priority', 'status', 'requester_id',
    'requester_name', 'requester_email', 'assigned_to', 'created_by', 'last_reply_at', 'resolved_at', 'closed_at',
])]
class Ticket extends Model
{
    use LogsActivity;

    protected $attributes = ['status' => 'open', 'priority' => 'normal'];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'last_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** REQ §7 "Ticket History". */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tickets')
            ->logOnly(['subject', 'category_id', 'priority', 'status', 'assigned_to'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    /** @return HasMany<TicketReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest()->oldest('id');
    }

    public function requesterName(): string
    {
        return $this->requester->name ?? $this->requester_name ?? __('Unknown');
    }

    public function requesterEmail(): ?string
    {
        return $this->requester->email ?? $this->requester_email;
    }
}
