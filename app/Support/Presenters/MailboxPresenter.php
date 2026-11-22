<?php

namespace App\Support\Presenters;

use App\Models\MailboxMessage;
use App\Support\Email\EmailHtml;

/** Shapes received email for the panel. Never includes mailbox passwords or storage paths. */
class MailboxPresenter
{
    /** @return array<string, mixed> */
    public static function row(MailboxMessage $m): array
    {
        return [
            'id' => $m->id,
            'folder' => $m->folder,
            'from_email' => $m->from_email,
            'from_name' => $m->from_name,
            'subject' => $m->subject !== null && $m->subject !== '' ? $m->subject : null,
            'preview' => $m->preview,
            'is_read' => $m->is_read,
            'has_attachments' => $m->has_attachments,
            'received_at' => $m->received_at?->toIso8601String(),
            'mailbox' => $m->relationLoaded('account') ? $m->account->email : null,
        ];
    }

    /**
     * @param  callable(int): string  $inlineUrl  URL of an embedded picture, by attachment position
     * @param  callable(int): string  $downloadUrl
     * @return array<string, mixed>
     */
    public static function detail(MailboxMessage $m, callable $inlineUrl, callable $downloadUrl): array
    {
        return [
            ...self::row($m),
            'to' => $m->to ?? [],
            'cc' => $m->cc ?? [],
            'reply_to' => $m->reply_to,
            'body_html' => $m->body_html !== null ? EmailHtml::resolveInline($m->body_html, $inlineUrl) : null,
            'body_text' => $m->body_text,
            'attachments' => collect($m->attachments ?? [])
                ->map(fn (array $a, int $i) => ['index' => $i, 'name' => $a['name'], 'mime' => $a['mime'], 'size' => $a['size'], 'inline' => $a['inline'], 'available' => $a['path'] !== '', 'url' => $downloadUrl($i)])
                ->filter(fn (array $a) => ! $a['inline'])->values()->all(),
        ];
    }
}
