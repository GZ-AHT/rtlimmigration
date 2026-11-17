<?php

namespace App\Actions\Chat;

use App\Enums\ChatKind;
use App\Enums\ChatStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ChatConversation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ChatActivity;
use App\Support\Chat\ChatSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;

/**
 * Applies one verified Tawk.to webhook event to our chat records (REQ §18).
 *
 * Events can arrive late, twice or out of order, so every handler works
 * whether or not the conversation already exists, and none of them reopens
 * a conversation that has ended.
 */
class RecordChatEvent
{
    public const EVENTS = ['chat:start', 'chat:end', 'chat:transcript_created', 'ticket:create'];

    public function __construct(private ChatSettings $settings) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return string a short outcome for the webhook log
     */
    public function __invoke(array $payload): string
    {
        return match ($payload['event'] ?? null) {
            'chat:start' => $this->chatStarted($payload),
            'chat:end' => $this->chatEnded($payload),
            'chat:transcript_created' => $this->transcriptCreated($payload),
            'ticket:create' => $this->ticketCreated($payload),
            default => 'ignored: unknown event',
        };
    }

    /**
     * The chat or ticket an event is about, for the webhook log.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function externalId(array $payload): ?string
    {
        $id = $payload['chatId'] ?? Arr::get($payload, 'chat.id') ?? Arr::get($payload, 'ticket.id');

        return is_string($id) && $id !== '' ? mb_substr($id, 0, 64) : null;
    }

    /** @param  array<string, mixed>  $payload */
    private function chatStarted(array $payload): string
    {
        $id = self::externalId($payload);

        if ($id === null) {
            return 'ignored: no chat id';
        }

        $conversation = $this->conversation(ChatKind::Chat, $id);
        $isNew = ! $conversation->exists;

        $this->fillVisitor($conversation, (array) ($payload['visitor'] ?? []), $payload);
        $conversation->started_at ??= $this->time($payload['time'] ?? null);
        $conversation->first_message ??= $this->text(Arr::get($payload, 'message.text'));
        $conversation->message_count = max($conversation->message_count, 1);
        $linked = $this->link($conversation);
        $conversation->save();

        if ($isNew && $conversation->assignee) {
            $conversation->assignee->notify(new ChatActivity($conversation, 'started'));
        }

        return ($isNew ? 'chat recorded' : 'chat updated')." · {$linked}";
    }

    /** @param  array<string, mixed>  $payload */
    private function chatEnded(array $payload): string
    {
        $id = self::externalId($payload);

        if ($id === null) {
            return 'ignored: no chat id';
        }

        $conversation = $this->conversation(ChatKind::Chat, $id);

        $this->fillVisitor($conversation, (array) ($payload['visitor'] ?? []), $payload);
        $conversation->status = ChatStatus::Closed;
        $conversation->ended_at ??= $this->time($payload['time'] ?? null);
        $conversation->started_at ??= $conversation->ended_at;
        $linked = $this->link($conversation);
        $conversation->save();

        return "chat ended · {$linked}";
    }

    /** @param  array<string, mixed>  $payload */
    private function transcriptCreated(array $payload): string
    {
        $id = self::externalId($payload);

        if ($id === null) {
            return 'ignored: no chat id';
        }

        $conversation = $this->conversation(ChatKind::Chat, $id);
        $messages = $this->messages(Arr::get($payload, 'chat.messages'));

        $this->fillVisitor($conversation, (array) Arr::get($payload, 'chat.visitor', []), $payload);
        $conversation->messages = $messages;
        $conversation->message_count = count($messages);
        $conversation->first_message ??= collect($messages)->firstWhere('sender', 'visitor')['text'] ?? ($messages[0]['text'] ?? null);
        // Tawk.to only writes a transcript once the chat is over.
        $conversation->status = ChatStatus::Closed;
        $conversation->ended_at ??= $this->time($payload['time'] ?? null);
        $conversation->started_at ??= $this->time($messages[0]['time'] ?? null) ?? $conversation->ended_at;
        $linked = $this->link($conversation);
        $conversation->save();

        return 'transcript saved ('.count($messages)." messages) · {$linked}";
    }

    /** @param  array<string, mixed>  $payload */
    private function ticketCreated(array $payload): string
    {
        $id = self::externalId($payload);

        if ($id === null) {
            return 'ignored: no ticket id';
        }

        $conversation = $this->conversation(ChatKind::Offline, $id);

        if ($conversation->exists) {
            return 'offline message already recorded';
        }

        $this->fillVisitor($conversation, (array) ($payload['requester'] ?? []), $payload);
        $humanId = Arr::get($payload, 'ticket.humanId');
        $conversation->reference = is_scalar($humanId) ? mb_substr((string) $humanId, 0, 32) : null;
        $conversation->subject = $this->text(Arr::get($payload, 'ticket.subject'), 255);
        $conversation->first_message = $this->text(Arr::get($payload, 'ticket.message'));
        $conversation->message_count = 1;
        $conversation->started_at = $this->time($payload['time'] ?? null);
        $linked = $this->link($conversation);
        $conversation->save();

        // The student's counselor if we know them, otherwise whoever manages chat.
        $recipients = $conversation->assignee ? [$conversation->assignee] : User::query()
            ->where('status', UserStatus::Active->value)
            ->where(fn (Builder $q) => $q->permission('chat.view-all')->orWhereHas('roles', fn (Builder $r) => $r->where('name', Role::SuperAdmin->value)))
            ->get();

        Notification::send($recipients, new ChatActivity($conversation, 'offline'));

        return "offline message recorded · {$linked}";
    }

    private function conversation(ChatKind $kind, string $externalId): ChatConversation
    {
        return ChatConversation::firstOrNew(['kind' => $kind, 'external_id' => $externalId]);
    }

    /**
     * @param  array<string, mixed>  $visitor
     * @param  array<string, mixed>  $payload
     */
    private function fillVisitor(ChatConversation $conversation, array $visitor, array $payload): void
    {
        $email = $this->text($visitor['email'] ?? null, 255);

        $conversation->visitor_name = $this->text($visitor['name'] ?? null, 255) ?? $conversation->visitor_name;
        $conversation->visitor_email = $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_strtolower($email) : $conversation->visitor_email;
        $conversation->visitor_city = $this->text($visitor['city'] ?? null, 255) ?? $conversation->visitor_city;
        $conversation->visitor_country = $this->text($visitor['country'] ?? null, 8) ?? $conversation->visitor_country;
        $conversation->property_id = $this->text(Arr::get($payload, 'property.id'), 64) ?? $conversation->property_id;
        $conversation->property_name = $this->text(Arr::get($payload, 'property.name'), 255) ?? $conversation->property_name;
    }

    /**
     * Ties the conversation to a student by email, and to that student's
     * counselor (REQ §18 "Assign Chat to Counselor"). Only done with Secure
     * Mode on: without it a visitor can type any email into the widget, and
     * a typed email must not attach a chat to someone's student record.
     *
     * @return string who it is linked to, or why not, for the webhook log
     */
    private function link(ChatConversation $conversation): string
    {
        if ($conversation->student_id !== null) {
            return "student #{$conversation->student_id}";
        }

        if (! $this->settings->secureMode()) {
            return 'not linked: Secure Mode API key not saved';
        }

        if ($conversation->visitor_email === null) {
            return 'not linked: no visitor email';
        }

        $student = Student::query()
            ->whereHas('user', fn (Builder $q) => $q->where('email', $conversation->visitor_email))
            ->first();

        if ($student === null) {
            return 'not linked: no student with this email';
        }

        $conversation->student_id = $student->id;
        $conversation->assigned_to ??= $student->counselor_id;

        return "student #{$student->id}";
    }

    /**
     * Tawk.to's transcript, reduced to what we show.
     *
     * @return list<array{sender: string, name: string|null, text: string, time: string|null, attachments: list<array{name: string, url: string}>}>
     */
    private function messages(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $messages = [];

        foreach (array_slice(array_values($raw), 0, 2000) as $message) {
            if (! is_array($message)) {
                continue;
            }

            $attachments = [];

            foreach ((array) ($message['attchs'] ?? []) as $attachment) {
                $url = Arr::get($attachment, 'content.file.url');

                // Only web links: these are rendered as links for staff.
                if (is_string($url) && preg_match('#^https://#i', $url)) {
                    $attachments[] = ['name' => $this->text(Arr::get($attachment, 'content.file.name'), 255) ?? __('Attachment'), 'url' => mb_substr($url, 0, 2000)];
                }
            }

            $text = $this->text($message['msg'] ?? null) ?? '';

            if ($text === '' && $attachments === []) {
                continue;
            }

            $messages[] = [
                'sender' => match (Arr::get($message, 'sender.t')) {
                    'a' => 'agent',
                    'v' => 'visitor',
                    default => 'system',
                },
                'name' => $this->text(Arr::get($message, 'sender.n'), 120),
                'text' => $text,
                'time' => $this->time($message['time'] ?? null)?->toIso8601String(),
                'attachments' => $attachments,
            ];
        }

        return $messages;
    }

    private function text(mixed $value, int $limit = 5000): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        try {
            return is_int($value)
                ? CarbonImmutable::createFromTimestampMs($value > 9_999_999_999 ? $value : $value * 1000)
                : CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
