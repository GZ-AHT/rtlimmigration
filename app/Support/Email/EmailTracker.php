<?php

namespace App\Support\Email;

use App\Models\EmailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Open and click tracking for mail this system sends (REQ §3 "Email
 * Tracking (if supported)"). Opens are a 1x1 image; clicks pass through a
 * signed redirect, so the redirect cannot be abused to send people
 * elsewhere. Mail clients that block images make open counts a lower bound.
 */
class EmailTracker
{
    public function __construct(private EmailSettings $settings) {}

    public function apply(EmailMessage $message): string
    {
        if (! $this->settings->trackingEnabled()) {
            return $message->body;
        }

        $body = (string) preg_replace_callback(
            '~<a href="(https?://[^"]+)"~i',
            fn (array $m) => '<a href="'.e(URL::signedRoute('email.track.click', ['message' => $message->uuid, 'u' => html_entity_decode($m[1])])).'"',
            $message->body,
        );

        $pixel = '<img src="'.e(route('email.track.open', $message->uuid)).'" width="1" height="1" alt="" style="display:block;border:0">';

        return $body.$pixel;
    }
}
