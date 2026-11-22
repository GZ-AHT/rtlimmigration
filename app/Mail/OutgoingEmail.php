<?php

namespace App\Mail;

use App\Models\EmailMessage;
use App\Support\Email\EmailTracker;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** One logged email (REQ §3), rendered from its EmailMessage row. */
class OutgoingEmail extends Mailable
{
    public function __construct(public EmailMessage $record) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->record->from_email, $this->record->from_name),
            to: [new Address($this->record->to_email, $this->record->to_name)],
            cc: array_map(fn (string $email) => new Address($email), $this->record->cc ?? []),
            bcc: array_map(fn (string $email) => new Address($email), $this->record->bcc ?? []),
            subject: $this->record->subject,
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-RTL-Message' => $this->record->uuid]);
    }

    public function content(): Content
    {
        return new Content(htmlString: app(EmailTracker::class)->apply($this->record));
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (array $a) => Attachment::fromStorageDisk((string) config('documents.disk'), $a['path'])->as($a['name'])->withMime($a['mime']),
            $this->record->attachments ?? [],
        );
    }
}
