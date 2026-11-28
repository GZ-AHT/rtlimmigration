<?php

namespace App\Support\Consultations;

use App\Models\ConsultationBooking;
use App\Support\Settings\SettingsRepository;

/**
 * The admin-configured video consultation schedule, kept in the settings
 * store under "consultation". Customers only ever see what this allows.
 *
 * @phpstan-type Day array{enabled: bool, start: string, end: string}
 * @phpstan-type Blocked array{date: string, from: string|null, to: string|null, reason: string}
 */
class ConsultationSchedule
{
    public const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

    public const DURATIONS = [15, 30, 45, 60];

    public const MAX_BREAKS = 10;

    public const MAX_BLOCKED = 200;

    public function __construct(private SettingsRepository $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('consultation.enabled', true);
    }

    /** The zone the working hours are written in. */
    public function timezone(): string
    {
        $zone = (string) $this->settings->get('consultation.timezone', $this->settings->get('general.timezone', 'Asia/Dhaka'));

        return in_array($zone, timezone_identifiers_list(), true) ? $zone : 'Asia/Dhaka';
    }

    public function duration(): int
    {
        $value = (int) $this->settings->get('consultation.duration', 30);

        return in_array($value, self::DURATIONS, true) ? $value : 30;
    }

    /** Minutes kept free between two appointments. */
    public function buffer(): int
    {
        return max(0, min(120, (int) $this->settings->get('consultation.buffer', 10)));
    }

    /** How many hours ahead a booking must be made at the latest. */
    public function minNoticeHours(): int
    {
        return max(0, min(720, (int) $this->settings->get('consultation.min_notice_hours', 12)));
    }

    public function maxDaysAhead(): int
    {
        return max(1, min(365, (int) $this->settings->get('consultation.max_days_ahead', 60)));
    }

    /** Whether a new booking is confirmed at once instead of waiting for an admin. */
    public function autoConfirm(): bool
    {
        return (bool) $this->settings->get('consultation.auto_confirm', false);
    }

    /**
     * The link every new booking starts with, if one is saved.
     *
     * @return array{provider: string, url: string}|null
     */
    public function defaultMeeting(): ?array
    {
        $url = $this->settings->get('consultation.default_meeting_url');
        $provider = (string) $this->settings->get('consultation.default_meeting_provider', 'google_meet');

        return is_string($url) && $url !== ''
            ? ['provider' => array_key_exists($provider, ConsultationBooking::PROVIDERS) ? $provider : 'other', 'url' => $url]
            : null;
    }

    public function saveDefaultMeeting(string $provider, ?string $url): void
    {
        $this->settings->set('consultation.default_meeting_provider', $provider);
        $this->settings->set('consultation.default_meeting_url', $url);
    }

    /** @return array<string, array{enabled: bool, start: string, end: string}> */
    public function weekly(): array
    {
        $stored = $this->settings->get('consultation.weekly');
        $stored = is_array($stored) ? $stored : [];
        $week = [];

        foreach (array_keys(self::DAYS) as $key) {
            $default = ['enabled' => ! in_array($key, ['fri', 'sat'], true), 'start' => '10:00', 'end' => '18:00'];
            $day = is_array($stored[$key] ?? null) ? $stored[$key] : [];

            $week[$key] = [
                'enabled' => (bool) ($day['enabled'] ?? $default['enabled']),
                'start' => self::time($day['start'] ?? null) ?? $default['start'],
                'end' => self::time($day['end'] ?? null) ?? $default['end'],
            ];
        }

        return $week;
    }

    /** @return list<array{start: string, end: string}> daily breaks, e.g. lunch */
    public function breaks(): array
    {
        $stored = $this->settings->get('consultation.breaks');

        if (! is_array($stored)) {
            return [['start' => '13:00', 'end' => '14:00']];
        }

        $breaks = [];

        foreach ($stored as $b) {
            $start = is_array($b) ? self::time($b['start'] ?? null) : null;
            $end = is_array($b) ? self::time($b['end'] ?? null) : null;

            if ($start !== null && $end !== null && $start < $end) {
                $breaks[] = ['start' => $start, 'end' => $end];
            }
        }

        return $breaks;
    }

    /** @return list<array{date: string, from: string|null, to: string|null, reason: string}> whole days (no times) or time ranges that are not available */
    public function blocked(): array
    {
        $stored = $this->settings->get('consultation.blocked');
        $blocked = [];

        foreach (is_array($stored) ? $stored : [] as $b) {
            if (! is_array($b) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($b['date'] ?? ''))) {
                continue;
            }

            $from = self::time($b['from'] ?? null);
            $to = self::time($b['to'] ?? null);
            $blocked[] = ['date' => (string) $b['date'], 'from' => $from !== null && $to !== null ? $from : null, 'to' => $from !== null && $to !== null ? $to : null, 'reason' => (string) ($b['reason'] ?? '')];
        }

        return $blocked;
    }

    /** @return array<string, mixed> */
    public function forAdmin(): array
    {
        return [
            'enabled' => $this->enabled(),
            'timezone' => $this->timezone(),
            'duration' => $this->duration(),
            'buffer' => $this->buffer(),
            'min_notice_hours' => $this->minNoticeHours(),
            'max_days_ahead' => $this->maxDaysAhead(),
            'auto_confirm' => $this->autoConfirm(),
            'weekly' => $this->weekly(),
            'breaks' => $this->breaks(),
            'blocked' => $this->blocked(),
        ];
    }

    /** @param  array<string, mixed>  $data validated */
    public function save(array $data): void
    {
        foreach (['enabled', 'timezone', 'duration', 'buffer', 'min_notice_hours', 'max_days_ahead', 'auto_confirm', 'weekly', 'breaks', 'blocked'] as $key) {
            if (array_key_exists($key, $data)) {
                $this->settings->set("consultation.{$key}", $data[$key]);
            }
        }
    }

    /** "9:5" and "09:05" alike become "09:05"; anything else is null. */
    public static function time(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m) ? sprintf('%02d:%s', (int) $m[1], $m[2]) : null;
    }
}
