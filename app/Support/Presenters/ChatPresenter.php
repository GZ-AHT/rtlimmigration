<?php

namespace App\Support\Presenters;

use App\Actions\Chat\PortalMessaging;
use App\Enums\ModerationStatus;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Support\Chat\AutoReply\AutoReplySettings;
use App\Support\Chat\AutoReply\SupportAgent;
use App\Support\Chat\ChatSecuritySettings;
use App\Support\Chat\Security\ContentInspector;
use Closure;

class ChatPresenter
{
    /** @return array<string, mixed> */
    public static function row(ChatConversation $c): array
    {
        return [
            'id' => $c->id,
            'kind' => $c->kind->value,
            'kind_label' => $c->kind->label(),
            'kind_icon' => $c->kind->icon(),
            'reference' => $c->reference,
            'status' => $c->status->value,
            'status_label' => $c->status->labelFor($c->kind),
            'status_tone' => $c->status->toneFor($c->kind),
            'visitor' => $c->visitorLabel(),
            'visitor_email' => $c->visitor_email,
            'visitor_phone' => $c->visitor_phone,
            'handed_over' => $c->handed_over_at !== null,
            'location' => implode(', ', array_filter([$c->visitor_city, $c->visitor_country])) ?: null,
            'subject' => $c->subject,
            'preview' => $c->first_message ? (string) str($c->first_message)->squish()->limit(140) : null,
            'message_count' => $c->message_count,
            'tags' => $c->tags ?? [],
            'assignee' => $c->assignee?->name,
            'student' => $c->student ? ['id' => $c->student->id, 'number' => $c->student->student_number, 'name' => $c->student->user->name] : null,
            'started_at' => $c->started_at?->toIso8601String(),
            'ended_at' => $c->ended_at?->toIso8601String(),
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread_count' => (int) ($c->getAttribute('unread_count') ?? 0),
        ];
    }

    /**
     * Who is looking at a thread: their side of it, whether they may
     * moderate, and the visitor's name for guest chats.
     *
     * @return array{side: string, moderator: bool, visitor_name: string|null}
     */
    public static function viewer(string $side, bool $moderator = false, ?string $visitorName = null): array
    {
        return ['side' => $side, 'moderator' => $moderator, 'visitor_name' => $visitorName];
    }

    /**
     * One message for the browser. Attachments link to an authorised
     * download route, never to the storage path.
     *
     * A held message (pending, blocked, rejected) is shown in full to its
     * sender and to moderators; anyone else sees only that a message is
     * waiting for review. A sender is never told their message was flagged.
     *
     * @param  Closure(ChatMessageAttachment): string  $attachmentUrl
     * @param  array{side: string, moderator: bool, visitor_name: string|null}  $viewer
     * @return array<string, mixed>
     */
    public static function message(ChatMessage $m, Closure $attachmentUrl, array $viewer): array
    {
        $status = $m->moderation_status;
        $own = $m->sender_type === $viewer['side'];
        $redacted = $status->isHeld() && ! $own && ! $viewer['moderator'];

        [$state, $label] = match (true) {
            $status === ModerationStatus::Approved => ['delivered', null],
            $own && $status === ModerationStatus::Flagged => ['delivered', null],
            $own && $status === ModerationStatus::Pending => ['pending', __('Waiting for review')],
            $own => ['not_delivered', __('Not delivered: held by our safety checks')],
            $viewer['moderator'] => [$status->value, $status->label()],
            $status === ModerationStatus::Flagged => ['flagged', __('Flagged for review')],
            default => ['held', __('Held for review')],
        };

        return [
            'id' => $m->id,
            'client_id' => $own ? $m->client_id : null,
            'sender_type' => $m->sender_type,
            'automated' => $m->sender_type === ChatMessage::BOT,
            'sender_name' => match ($m->sender_type) {
                ChatMessage::SYSTEM => null,
                ChatMessage::BOT => app(AutoReplySettings::class)->assistantName(),
                ChatMessage::VISITOR => $viewer['visitor_name'] ?? __('Visitor'),
                ChatMessage::STAFF => $m->user === null && $m->user_id === null ? SupportAgent::name(null) : ($m->user ? SupportAgent::name($m->user) : __('Former user')),
                default => $m->user->name ?? __('Former user'),
            },
            'avatar' => match ($m->sender_type) {
                ChatMessage::STAFF => SupportAgent::avatarUrl($m->user),
                default => null,
            },
            'human' => $m->sender_type === ChatMessage::STAFF,
            'body' => $redacted ? null : $m->body,
            'attachments' => $redacted ? [] : $m->attachments->map(fn (ChatMessageAttachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'size' => $a->size,
                'mime_type' => $a->mime_type,
                'url' => $attachmentUrl($a),
            ])->all(),
            'moderation' => [
                'state' => $state,
                'label' => $label,
                'reasons' => $viewer['moderator'] ? self::reasonLabels($m->moderation_reasons ?? []) : [],
            ],
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public static function reasonLabels(array $codes): array
    {
        return array_map(fn (string $c) => ContentInspector::REASONS[$c] ?? $c, $codes);
    }

    /**
     * Limits the message box enforces before the server checks them again.
     * Chat is text-only until a Super Admin switches on file types, and
     * website visitors can never attach files.
     *
     * @return array{max_body: int, max_files: int, max_file_kb: int, accept: string}
     */
    public static function composer(bool $guest = false): array
    {
        $settings = app(ChatSecuritySettings::class);
        $extensions = array_keys($settings->allowedExtensions());

        return [
            'max_body' => PortalMessaging::MAX_BODY,
            'max_files' => $guest || $extensions === [] ? 0 : $settings->int('max_files'),
            'max_file_kb' => $settings->int('max_attachment_kb'),
            'accept' => implode(',', array_map(fn (string $ext): string => '.'.$ext, $extensions)),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(ChatConversation $c): array
    {
        return [
            ...self::row($c),
            'external_id' => $c->external_id,
            'property_name' => $c->property_name,
            'first_message' => $c->first_message,
            'messages' => $c->messages ?? [],
            'assigned_to' => $c->assigned_to,
            'lead' => $c->lead ? ['id' => $c->lead->id, 'number' => $c->lead->lead_number] : null,
        ];
    }
}
