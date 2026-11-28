<?php

namespace App\Support\Consultations;

use App\Models\ConsultationBooking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A meeting link must be a real https address, and a Google Meet or Zoom link
 * must be on that service's own domain. "Custom link" accepts any https
 * address. Links are entered by hand: nothing here creates a meeting.
 */
class MeetingLinkRules
{
    /** @return array<string, mixed> the validated meeting_provider and meeting_url */
    public static function validate(Request $request, bool $required): array
    {
        $data = $request->validate([
            'meeting_provider' => [$required ? 'required' : 'nullable', Rule::in(array_keys(ConsultationBooking::PROVIDERS))],
            'meeting_url' => [$required ? 'required' : 'nullable', 'string', 'url:https', 'max:500'],
        ]);

        if (filled($data['meeting_url'] ?? null)) {
            $host = strtolower((string) parse_url($data['meeting_url'], PHP_URL_HOST));
            $provider = $data['meeting_provider'] ?? 'other';

            $valid = match ($provider) {
                'google_meet' => $host === 'meet.google.com',
                'zoom' => $host === 'zoom.us' || str_ends_with($host, '.zoom.us'),
                default => true,
            };

            if (! $valid) {
                throw ValidationException::withMessages(['meeting_url' => $provider === 'zoom' ? __('A Zoom link must be on zoom.us.') : __('A Google Meet link must be on meet.google.com.')]);
            }
        }

        return $data;
    }
}
