<?php

namespace App\Mail;

use App\Support\Auth\Otp;
use App\Support\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The six-digit code email for verifying an address or resetting a password. */
class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public string $recipientName,
        public string $code,
        public string $purpose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === Otp::PASSWORD_RESET
            ? __('Your :site password reset code', ['site' => $this->siteName()])
            : __('Your :site verification code', ['site' => $this->siteName()]));
    }

    public function content(): Content
    {
        $settings = app(SettingsRepository::class);
        $colour = (string) $settings->get('branding.primary_color', '#d71920');

        return new Content(view: 'mail.otp', with: [
            'site' => $this->siteName(),
            'colour' => preg_match('/^#[0-9a-f]{6}$/i', $colour) ? $colour : '#d71920',
            'reset' => $this->purpose === Otp::PASSWORD_RESET,
            'minutes' => Otp::TTL_MINUTES,
        ]);
    }

    private function siteName(): string
    {
        return (string) app(SettingsRepository::class)->get('general.site_name', config('app.name'));
    }
}
