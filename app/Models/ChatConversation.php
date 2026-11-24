<?php

namespace App\Models;

use App\Enums\ChatKind;
use App\Enums\ChatStatus;
use App\Enums\ModerationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A Tawk.to chat or offline message, as reported by webhooks (REQ §18
 * "Chat History and Transcripts", "Offline Messages"), or a student's
 * portal message thread (kind "portal", one per student, messages in
 * chat_messages).
 *
 * @property int $id
 * @property ChatKind $kind
 * @property string $external_id
 * @property string|null $reference
 * @property ChatStatus $status
 * @property string|null $property_id
 * @property string|null $property_name
 * @property string|null $visitor_name
 * @property string|null $visitor_email
 * @property string|null $visitor_phone website chat phone number, E.164
 * @property CarbonInterface|null $handed_over_at when the chat was handed from the assistant to a person
 * @property string|null $visitor_city
 * @property string|null $visitor_country
 * @property string|null $subject
 * @property string|null $first_message
 * @property list<array{sender: string, name: string|null, text: string, time: string|null, attachments: list<array{name: string, url: string}>}>|null $messages
 * @property int $message_count
 * @property int|null $last_message_id
 * @property CarbonInterface|null $last_message_at
 * @property int $last_seq
 * @property list<string>|null $tags
 * @property int|null $student_id
 * @property int|null $lead_id
 * @property int|null $assigned_to
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $ended_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Student|null $student
 * @property-read Lead|null $lead
 * @property-read User|null $assignee
 * @property-read Collection<int, ChatMessage> $chatMessages
 * @property-read ChatGuest|null $guest
 */
#[Fillable([
    'kind', 'external_id', 'reference', 'status', 'property_id', 'property_name', 'visitor_name', 'visitor_email', 'visitor_phone', 'handed_over_at',
    'visitor_city', 'visitor_country', 'subject', 'first_message', 'messages', 'message_count', 'tags',
    'student_id', 'lead_id', 'assigned_to', 'started_at', 'ended_at', 'last_message_id', 'last_message_at', 'last_seq',
])]
class ChatConversation extends Model
{
    use LogsActivity;

    /** Suggested topics for tagging conversations; any other tag is allowed too. */
    public const TOPICS = ['Visa', 'Admission', 'Payment', 'Documents'];

    protected $attributes = ['kind' => 'chat', 'status' => 'open', 'message_count' => 0, 'last_seq' => 0];

    protected function casts(): array
    {
        return [
            'kind' => ChatKind::class,
            'status' => ChatStatus::class,
            'messages' => 'array',
            'tags' => 'array',
            'message_count' => 'integer',
            'last_message_id' => 'integer',
            'last_seq' => 'integer',
            'last_message_at' => 'datetime',
            'started_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** Staff changes only; the transcript itself is not copied into the log. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('chat')
            ->logOnly(['status', 'assigned_to', 'tags', 'lead_id', 'student_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /** @return HasMany<ChatMessage, $this> */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /** @return HasOne<ChatGuest, $this> */
    public function guest(): HasOne
    {
        return $this->hasOne(ChatGuest::class);
    }

    public function isPortal(): bool
    {
        return $this->kind === ChatKind::Portal;
    }

    public function isGuest(): bool
    {
        return $this->kind === ChatKind::Guest;
    }

    /** Messages stored here and answered from the dashboard (portal or website chat). */
    public function isNative(): bool
    {
        return $this->kind->isNative();
    }

    /** The fixed external ID of a student's portal thread, so there is only ever one. */
    public static function portalKey(int $studentId): string
    {
        return "student-{$studentId}";
    }

    /**
     * Adds unread_count: messages from the other side newer than what the
     * user has read. Staff count students' and visitors' messages; a
     * student counts staff messages. Messages held by moderation do not
     * count until they are approved.
     *
     * @param  Builder<self>  $query
     * @param  string|list<string>  $fromSender
     */
    public function scopeWithUnreadFor(Builder $query, User $user, string|array $fromSender): void
    {
        $query->addSelect(['unread_count' => ChatMessage::query()
            ->selectRaw('count(*)')
            ->whereColumn('chat_messages.chat_conversation_id', 'chat_conversations.id')
            ->whereIn('chat_messages.sender_type', (array) $fromSender)
            ->whereNotIn('chat_messages.moderation_status', ModerationStatus::heldValues())
            ->whereRaw('chat_messages.id > coalesce((select last_read_message_id from chat_reads where chat_reads.chat_conversation_id = chat_conversations.id and chat_reads.user_id = ?), 0)', [$user->id]),
        ]);
    }

    public function visitorLabel(): string
    {
        return $this->visitor_name ?: ($this->visitor_email ?: ($this->visitor_phone ?: __('Visitor')));
    }

    /**
     * Conversations the user may see: everything for chat managers,
     * otherwise those assigned to them or belonging to their students.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('chat.view-all')) {
            return;
        }

        $query->where(fn (Builder $q) => $q
            ->where('assigned_to', $user->id)
            ->orWhereHas('student', fn (Builder $s) => $s->where('counselor_id', $user->id)));
    }
}
