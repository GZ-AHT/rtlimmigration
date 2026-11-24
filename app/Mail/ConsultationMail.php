<?php

namespace App\Mail;

use App\Models\ConsultationBooking;
use App\Support\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Every email a customer gets about a video consultation: request received,
 * confirmed, meeting link, rescheduled, cancelled, declined and the reminder.
 * Queued, so it goes out through the configured mail and queue settings.
 * Times are shown in the customer's own timezone.
 */
class ConsultationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const KINDS = ['received', 'confirmed', 'meeting', 'rescheduled', 'cancelled', 'declined', 'reminder'];

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /** @param  array<string, mixed>  $extra  e.g. reason, previous time */
    public function __construct(public ConsultationBooking $booking, public string $kind, public array $extra = []) {}

    public function envelope(): Envelope
    {
        $site = $this->site();

        return new Envelope(subject: match ($this->kind) {
            'received' => __('We received your consultation request (:ref)', ['ref' => $this->booking->reference]),
            'confirmed' => __('Your video consultation is confirmed (:ref)', ['ref' => $this->booking->reference]),
            'meeting' => __('Your video consultation link (:ref)', ['ref' => $this->booking->reference]),
            'rescheduled' => __('Your video consultation was rescheduled (:ref)', ['ref' => $this->booking->reference]),
            'cancelled' => __('Your video consultation was cancelled (:ref)', ['ref' => $this->booking->reference]),
            'declined' => __('About your consultation request (:ref)', ['ref' => $this->booking->reference]),
            default => __('Reminder: your video consultation with :site (:ref)', ['site' => $site, 'ref' => $this->booking->reference]),
        });
    }

    public function content(): Content
    {
        $settings = app(SettingsRepository::class);
        $colour = (string) $settings->get('branding.primary_color', '#d71920');
        $b = $this->booking;

        [$heading, $lines] = match ($this->kind) {
            'received' => [__('We received your request'), [__('Thank you for booking a video consultation. Our team will review your request and email you when it is confirmed. Nothing is confirmed until then.')]],
            'confirmed' => [__('Your consultation is confirmed'), [__('Your video consultation is confirmed. We will email the meeting link if it is not below.')]],
            'meeting' => [__('Your meeting link'), [__('Here are the details for your video consultation.')]],
            'rescheduled' => [__('Your consultation was rescheduled'), [__('Your appointment has moved to a new time. Please note the new details below.')]],
            'cancelled' => [__('Your consultation was cancelled'), array_filter([__('Your video consultation has been cancelled.'), (string) ($this->extra['reason'] ?? '')])],
            'declined' => [__('We could not confirm this request'), array_filter([__('We are sorry, we are unable to take this consultation request. You are welcome to book another time.'), (string) ($this->extra['reason'] ?? '')])],
            default => [__('Your consultation is coming up'), [__('This is a reminder of your video consultation.')]],
        };

        $active = ! in_array($this->kind, ['cancelled', 'declined'], true);

        return new Content(view: 'mail.consultation', with: [
            'site' => $this->site(),
            'colour' => preg_match('/^#[0-9a-f]{6}$/i', $colour) ? $colour : '#d71920',
            'heading' => $heading,
            'lines' => $lines,
            'booking' => $b,
            'when' => $b->starts_at->setTimezone($b->timezone)->format('l, j F Y'),
            'time' => $b->starts_at->setTimezone($b->timezone)->format('g:i A').' – '.$b->ends_at->setTimezone($b->timezone)->format('g:i A'),
            'previous' => isset($this->extra['previous']) ? (string) $this->extra['previous'] : null,
            'showLink' => $active && $b->meeting_url !== null && in_array($this->kind, ['confirmed', 'meeting', 'rescheduled', 'reminder'], true),
            'manageUrl' => $active ? URL::signedRoute('consultation.manage', ['reference' => $b->reference]) : null,
            'provider' => ConsultationBooking::PROVIDERS[$b->meeting_provider ?? ''] ?? __('Video call'),
        ]);
    }

    private function site(): string
    {
        return (string) app(SettingsRepository::class)->get('general.site_name', config('app.name'));
    }
}
