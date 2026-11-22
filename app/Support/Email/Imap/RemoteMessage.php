<?php

namespace App\Support\Email\Imap;

use Carbon\CarbonInterface;

/** One message as read from the mail server, before it is stored. */
final class RemoteMessage
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<array{name: string, mime: string, content: string, cid: string|null, inline: bool}>  $attachments
     */
    public function __construct(
        public readonly int $uid,
        public readonly ?string $messageId,
        public readonly ?string $fromEmail,
        public readonly ?string $fromName,
        public readonly array $to,
        public readonly array $cc,
        public readonly ?string $replyTo,
        public readonly string $subject,
        public readonly ?CarbonInterface $date,
        public readonly ?string $text,
        public readonly ?string $html,
        public readonly array $attachments,
        public readonly bool $seen,
    ) {}
}
