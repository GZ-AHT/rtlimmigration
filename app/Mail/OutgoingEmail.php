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
use Illuminate\Support\Facades\URL;

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
        $headers = ['X-RTL-Message' => $this->record->uuid];

        // RFC 8058: mail apps show their own "Unsubscribe" button for bulk mail.
        if ($this->isBulk()) {
            $headers['List-Unsubscribe'] = '<'.$this->unsubscribeUrl().'>';
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(text: $headers);
    }

    public function content(): Content
    {
        $html = app(EmailTracker::class)->apply($this->record);

        if ($this->isBulk()) {
            // Added after tracking, so the link is never rewritten into a click redirect.
            $html .= '<p style="margin:24px 0 0;padding-top:12px;border-top:1px solid #e5e3e0;font:12px/1.5 Arial,sans-serif;color:#6b7280">'
                .e(__('You get this because you are a student or enquirer of :app.', ['app' => config('app.name')])).' '
                .'<a href="'.e($this->unsubscribeUrl()).'" style="color:#6b7280">'.e(__('Unsubscribe from bulk email')).'</a></p>';
        }

        return new Content(htmlString: $html);
    }

    /** Only campaign emails; receipts, replies and one-to-one mail carry no unsubscribe link. */
    private function isBulk(): bool
    {
        return $this->record->email_campaign_id !== null;
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('email.unsubscribe', ['message' => $this->record->uuid]);
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
