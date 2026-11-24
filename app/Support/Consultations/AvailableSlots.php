<?php

namespace App\Support\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\ConsultationBooking;
use Carbon\CarbonImmutable;

/**
 * Works out which appointment times can be booked, from the admin schedule
 * and the bookings that already hold a time. This is the only place that
 * says what is available: the calendar shows it, and a booking is accepted
 * only if its start is in this list.
 */
class AvailableSlots
{
    public function __construct(private ConsultationSchedule $schedule) {}

    /**
     * Every free start time between two instants, earliest first.
     *
     * @return list<CarbonImmutable> UTC
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, ?int $ignoreBookingId = null): array
    {
        if (! $this->schedule->enabled()) {
            return [];
        }

        $now = CarbonImmutable::now('UTC');
        $earliest = $now->addHours($this->schedule->minNoticeHours());
        $latest = $now->addDays($this->schedule->maxDaysAhead())->endOfDay();
        $from = $from->max($earliest);
        $to = $to->min($latest);

        if ($from >= $to) {
            return [];
        }

        $zone = $this->schedule->timezone();
        $duration = $this->schedule->duration();
        $buffer = $this->schedule->buffer();
        $weekly = $this->schedule->weekly();
        $taken = $this->taken($from->subMinutes($duration + $buffer), $to->addMinutes($duration + $buffer), $ignoreBookingId);
        $slots = [];

        // Walk the business days that touch the range.
        for ($day = $from->setTimezone($zone)->startOfDay(); $day < $to; $day = $day->addDay()->startOfDay()) {
            $config = $weekly[strtolower(substr($day->format('D'), 0, 3))] ?? null;

            if ($config === null || ! $config['enabled']) {
                continue;
            }

            $date = $day->format('Y-m-d');
            $end = $day->setTimeFromTimeString($config['end']);

            for ($start = $day->setTimeFromTimeString($config['start']); $start->addMinutes($duration) <= $end; $start = $start->addMinutes($duration + $buffer)) {
                $slotEnd = $start->addMinutes($duration);
                $utc = $start->setTimezone('UTC');

                if ($utc < $from || $utc >= $to || $this->inBreakOrBlocked($date, $start, $slotEnd, $day) || $this->collides($utc, $utc->addMinutes($duration), $buffer, $taken)) {
                    continue;
                }

                $slots[] = $utc;
            }
        }

        return $slots;
    }

    /** Whether this exact start is bookable right now. */
    public function isAvailable(CarbonImmutable $start, ?int $ignoreBookingId = null): bool
    {
        foreach ($this->between($start->subMinute(), $start->addMinute(), $ignoreBookingId) as $slot) {
            if ($slot->equalTo($start)) {
                return true;
            }
        }

        return false;
    }

    /** Whether another booking is too close to this window (used again under lock when booking). */
    public function conflicts(CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreBookingId = null): bool
    {
        $buffer = $this->schedule->buffer();

        return $this->collides($start, $end, $buffer, $this->taken($start->subMinutes($buffer + 1), $end->addMinutes($buffer + 1), $ignoreBookingId));
    }

    /** @return list<array{0: CarbonImmutable, 1: CarbonImmutable}> */
    private function taken(CarbonImmutable $from, CarbonImmutable $to, ?int $ignoreBookingId): array
    {
        $rows = ConsultationBooking::query()
            ->whereIn('status', ConsultationStatus::holding())
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->get(['starts_at', 'ends_at']);

        $taken = [];

        foreach ($rows as $row) {
            $taken[] = [$row->starts_at, $row->ends_at];
        }

        return $taken;
    }

    /** @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $taken */
    private function collides(CarbonImmutable $start, CarbonImmutable $end, int $buffer, array $taken): bool
    {
        foreach ($taken as [$s, $e]) {
            if ($start < $e->addMinutes($buffer) && $end > $s->subMinutes($buffer)) {
                return true;
            }
        }

        return false;
    }

    private function inBreakOrBlocked(string $date, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $day): bool
    {
        foreach ($this->schedule->breaks() as $b) {
            if ($start < $day->setTimeFromTimeString($b['end']) && $end > $day->setTimeFromTimeString($b['start'])) {
                return true;
            }
        }

        foreach ($this->schedule->blocked() as $b) {
            if ($b['date'] !== $date) {
                continue;
            }

            if ($b['from'] === null || ($start < $day->setTimeFromTimeString((string) $b['to']) && $end > $day->setTimeFromTimeString($b['from']))) {
                return true;
            }
        }

        return false;
    }
}
