<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Mail\ConsultationMail;
use App\Models\ConsultationBooking;
use App\Models\ConsultationBookingEvent;
use App\Models\User;
use App\Notifications\ConsultationBooked;
use App\Notifications\ConsultationCancelled;
use App\Notifications\ConsultationRescheduleRequested;
use App\Support\Consultations\AvailableSlots;
use App\Support\Consultations\ConsultationSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Everything that happens to a video consultation booking. Each change is
 * written to the booking's history, and customers are emailed through the
 * queued ConsultationMail. Times are UTC.
 */
class ConsultationBookings
{
    public function __construct(private ConsultationSchedule $schedule, private AvailableSlots $slots) {}

    /**
     * Books a time for a verified customer. The slot must be one the schedule offers
     * right now, and nobody else may hold it: neighbouring bookings are locked while we
     * check, and a unique index on the start time backs that up.
     *
     * @param  array{name: string, email: string, phone: string, subject: string, message: string|null, timezone: string}  $details
     * @param  array{user_id: int|null, lead_id: int|null}  $customer
     */
    public function book(array $details, CarbonImmutable $start, array $customer): ConsultationBooking
    {
        $start = $start->utc();
        $end = $start->addMinutes($this->schedule->duration());
        $buffer = $this->schedule->buffer();
        $confirmed = $this->schedule->autoConfirm();
        $taken = ValidationException::withMessages(['starts_at' => __('Sorry, that time was just taken. Please pick another.')]);
        $meeting = $this->schedule->defaultMeeting();

        try {
            $booking = DB::transaction(function () use ($details, $start, $end, $buffer, $confirmed, $customer, $taken, $meeting) {
                ConsultationBooking::query()->whereIn('status', ConsultationStatus::holding())
                    ->where('starts_at', '<', $end->addMinutes($buffer))->where('ends_at', '>', $start->subMinutes($buffer))
                    ->lockForUpdate()->get();

                if (! $this->slots->isAvailable($start)) {
                    throw $taken;
                }

                $booking = ConsultationBooking::query()->create([
                    ...$details,
                    ...$customer,
                    'reference' => ConsultationBooking::newReference(),
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'duration_minutes' => $this->schedule->duration(),
                    'status' => $confirmed ? ConsultationStatus::Confirmed : ConsultationStatus::Pending,
                    'active_slot' => $start,
                    'confirmed_at' => $confirmed ? now() : null,
                    'meeting_provider' => $meeting['provider'] ?? null,
                    'meeting_url' => $meeting['url'] ?? null,
                    'meeting_is_default' => $meeting !== null,
                ]);

                $this->log($booking, 'created', null, null, ['auto_confirmed' => $confirmed]);

                return $booking;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw $taken;
        }

        // The booking is saved: a problem sending the emails is reported, but the visitor still gets their confirmation.
        if ($confirmed) {
            $this->sendConfirmation($booking);
        } else {
            try {
                $this->mail($booking, 'received');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->notifyStaff(new ConsultationBooked($booking));

        return $booking;
    }

    /**
     * Approves a pending request and sends the one confirmation email, which carries the
     * meeting link when there is one: the link entered now, else the booking's own, else
     * the saved default. Returns false when the email could not be sent (the approval stands).
     */
    public function approve(ConsultationBooking $booking, User $by, ?string $provider = null, ?string $url = null): bool
    {
        $message = __('Only a pending request can be approved.');
        $this->requireStatus($booking, [ConsultationStatus::Pending], $message);

        // Only one of two simultaneous approvals gets through, so the customer is emailed once.
        $claimed = ConsultationBooking::query()->whereKey($booking->id)->where('status', ConsultationStatus::Pending->value)
            ->update(['status' => ConsultationStatus::Confirmed->value, 'confirmed_at' => now()]);

        if ($claimed === 0) {
            throw ValidationException::withMessages(['status' => $message]);
        }

        $booking->refresh();
        $this->log($booking, 'approved', $by);

        if ($url !== null) {
            $this->setMeeting($booking, $by, $provider, $url);
        } elseif ($booking->meeting_url === null && ($default = $this->schedule->defaultMeeting()) !== null) {
            $this->setMeeting($booking, $by, $default['provider'], $default['url'], isDefault: true);
        }

        return $this->sendConfirmation($booking, $by);
    }

    /** Gives a booking its counselor, changes them, or (null) removes them. No email is sent. */
    public function assignCounselor(ConsultationBooking $booking, User $by, ?User $counselor): void
    {
        $this->requireStatus($booking, [ConsultationStatus::Pending, ConsultationStatus::Confirmed], __('The counselor can no longer be changed.'));

        if ($counselor && (! $counselor->hasRole(Role::Counselor->value) || ! $counselor->isActive())) {
            throw ValidationException::withMessages(['counselor_id' => __('Only an active counselor can be assigned.')]);
        }

        if ($booking->counselor_id === $counselor?->id) {
            return;
        }

        $previous = $booking->counselor?->name;
        $booking->forceFill(['counselor_id' => $counselor?->id])->save();
        $booking->unsetRelation('counselor');
        $this->log($booking, 'counselor', $by, null, ['from' => $previous, 'to' => $counselor?->name]);
    }

    /**
     * Saves the default meeting link. Requests still waiting for approval follow it, unless
     * a link was entered for them; confirmed bookings keep what the customer was told.
     */
    public function saveDefaultMeeting(User $by, string $provider, ?string $url): void
    {
        $this->schedule->saveDefaultMeeting($provider, $url);

        $waiting = ConsultationBooking::query()->where('status', ConsultationStatus::Pending->value)
            ->where(fn ($q) => $q->whereNull('meeting_url')->orWhere('meeting_is_default', true))->get();

        foreach ($waiting as $booking) {
            if ($url !== null) {
                $this->setMeeting($booking, $by, $provider, $url, isDefault: true);
            } elseif ($booking->meeting_url !== null) {
                $booking->forceFill(['meeting_provider' => null, 'meeting_url' => null, 'meeting_is_default' => false, 'meeting_sent_at' => null])->save();
            }
        }
    }

    /** Declines a pending request: the slot is freed. */
    public function reject(ConsultationBooking $booking, User $by, ?string $reason): void
    {
        $this->requireStatus($booking, [ConsultationStatus::Pending], __('Only a pending request can be rejected.'));

        $this->release($booking, ConsultationStatus::Cancelled);
        $this->log($booking, 'rejected', $by, $reason);
        $this->mail($booking, 'declined', ['reason' => $reason]);
    }

    /** Cancels a pending or confirmed appointment. `$by` is null when the customer did it. */
    public function cancel(ConsultationBooking $booking, ?User $by, ?string $reason): void
    {
        $this->requireStatus($booking, [ConsultationStatus::Pending, ConsultationStatus::Confirmed], __('This appointment can no longer be cancelled.'));

        $this->release($booking, ConsultationStatus::Cancelled);
        $this->log($booking, 'cancelled', $by, $reason, ['by' => $by ? 'admin' : 'customer']);

        if ($by === null) {
            $this->notifyStaff(new ConsultationCancelled($booking));
        } else {
            $this->mail($booking, 'cancelled', ['reason' => $reason]);
        }
    }

    public function complete(ConsultationBooking $booking, User $by): void
    {
        $this->requireStatus($booking, [ConsultationStatus::Confirmed], __('Only a confirmed appointment can be completed.'));

        $booking->forceFill(['status' => ConsultationStatus::Completed, 'active_slot' => null])->save();
        $this->log($booking, 'completed', $by);
    }

    /**
     * Moves an appointment. Staff may choose any future time that does not clash with another
     * appointment (it need not be in the public schedule). The customer is emailed.
     */
    public function reschedule(ConsultationBooking $booking, User $by, CarbonImmutable $start, ?string $note = null): void
    {
        $this->requireStatus($booking, [ConsultationStatus::Pending, ConsultationStatus::Confirmed], __('This appointment can no longer be moved.'));

        $start = $start->utc();
        $end = $start->addMinutes($booking->duration_minutes);
        $busy = ValidationException::withMessages(['starts_at' => __('That time clashes with another appointment.')]);

        if ($start->isPast()) {
            throw ValidationException::withMessages(['starts_at' => __('Choose a time in the future.')]);
        }

        $previous = $booking->localTime();

        try {
            DB::transaction(function () use ($booking, $start, $end, $busy) {
                $buffer = $this->schedule->buffer();
                ConsultationBooking::query()->whereIn('status', ConsultationStatus::holding())->whereKeyNot($booking->id)
                    ->where('starts_at', '<', $end->addMinutes($buffer))->where('ends_at', '>', $start->subMinutes($buffer))
                    ->lockForUpdate()->get();

                if ($this->slots->conflicts($start, $end, $booking->id)) {
                    throw $busy;
                }

                $booking->forceFill(['starts_at' => $start, 'ends_at' => $end, 'active_slot' => $start, 'reminder_sent_at' => null])->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw $busy;
        }

        $this->log($booking, 'rescheduled', $by, $note, ['from' => $previous, 'to' => $booking->localTime()]);
        $this->mail($booking, 'rescheduled', ['previous' => $previous]);
    }

    /** Saving the link a booking already has changes nothing, so it is not seen as unsent again. */
    public function setMeeting(ConsultationBooking $booking, User $by, ?string $provider, string $url, bool $isDefault = false): void
    {
        $provider = array_key_exists((string) $provider, ConsultationBooking::PROVIDERS) ? (string) $provider : 'other';

        if ($booking->meeting_url === $url && $booking->meeting_provider === $provider) {
            return;
        }

        $replaced = $booking->meeting_url !== null;

        $booking->forceFill(['meeting_provider' => $provider, 'meeting_url' => $url, 'meeting_is_default' => $isDefault, 'meeting_sent_at' => null])->save();
        $this->log($booking, 'meeting_link', $by, null, ['provider' => $provider, 'url' => $url, 'replaced' => $replaced, 'default' => $isDefault]);
    }

    /**
     * Emails the meeting link and appointment details to the customer's verified address.
     * Without `$resend`, a link that was already emailed is not sent again (returns false).
     */
    public function sendMeeting(ConsultationBooking $booking, User $by, bool $resend = true): bool
    {
        if ($booking->meeting_url === null) {
            throw ValidationException::withMessages(['meeting_url' => __('Add a meeting link first.')]);
        }

        $this->requireStatus($booking, [ConsultationStatus::Confirmed], __('Approve the booking before sending the meeting link.'));

        // The first send is claimed in the database, so two quick clicks send one email.
        if ($resend) {
            $booking->forceFill(['meeting_sent_at' => now()])->save();
        } elseif (ConsultationBooking::query()->whereKey($booking->id)->whereNull('meeting_sent_at')->update(['meeting_sent_at' => now()]) === 0) {
            return false;
        }

        $booking->refresh();
        $this->log($booking, $resend ? 'meeting_resent' : 'meeting_sent', $by);
        $this->mail($booking, 'meeting');

        return true;
    }

    public function note(ConsultationBooking $booking, User $by, string $note): void
    {
        $this->log($booking, 'note', $by, $note);
    }

    /** A customer asked for another time; the team is told and replies by email or reschedules. */
    public function requestReschedule(ConsultationBooking $booking, string $request): void
    {
        $this->requireStatus($booking, [ConsultationStatus::Pending, ConsultationStatus::Confirmed], __('This appointment can no longer be changed.'));

        $this->log($booking, 'reschedule_requested', null, $request);
        $this->notifyStaff(new ConsultationRescheduleRequested($booking, $request));
    }

    /** Sends the reminder for appointments starting within a day. Returns how many were sent. */
    public function sendReminders(): int
    {
        $due = ConsultationBooking::query()->where('status', ConsultationStatus::Confirmed->value)->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDay())->get();

        foreach ($due as $booking) {
            $booking->forceFill(['reminder_sent_at' => now()])->save();
            $this->log($booking, 'reminder_sent');
            $this->mail($booking, 'reminder');
        }

        return $due->count();
    }

    /** @param  array<string, mixed>|null  $data */
    public function log(ConsultationBooking $booking, string $type, ?User $by = null, ?string $note = null, ?array $data = null): ConsultationBookingEvent
    {
        return $booking->events()->create(['user_id' => $by?->id, 'type' => $type, 'note' => $note, 'data' => $data]);
    }

    /** A problem sending the email is reported and never undoes the confirmation. */
    private function sendConfirmation(ConsultationBooking $booking, ?User $by = null): bool
    {
        try {
            if ($booking->meeting_url !== null) {
                $booking->forceFill(['meeting_sent_at' => now()])->save();
            }

            $this->mail($booking, 'confirmed');
        } catch (\Throwable $e) {
            report($e);
            $booking->forceFill(['meeting_sent_at' => null])->save();

            return false;
        }

        $this->log($booking, 'confirmation_sent', $by);

        return true;
    }

    /** @param  array<string, mixed>  $extra */
    private function mail(ConsultationBooking $booking, string $kind, array $extra = []): void
    {
        Mail::to($booking->email)->send(new ConsultationMail($booking->fresh() ?? $booking, $kind, $extra));
    }

    private function release(ConsultationBooking $booking, ConsultationStatus $status): void
    {
        $booking->forceFill(['status' => $status, 'active_slot' => null, 'cancelled_at' => now()])->save();
    }

    /** @param  list<ConsultationStatus>  $allowed */
    private function requireStatus(ConsultationBooking $booking, array $allowed, string $message): void
    {
        if (! in_array($booking->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    /**
     * The people who manage consultations, plus Super Admins. Works before the permission has been
     * added to the database (a site that has not run the seeder yet): then only Super Admins.
     *
     * @return Collection<int, User>
     */
    private function staff(): Collection
    {
        $permissionExists = Permission::query()->where('name', 'consultations.manage')->where('guard_name', 'web')->exists();

        return User::query()->where('status', UserStatus::Active->value)
            ->where(function ($q) use ($permissionExists) {
                $q->whereHas('roles', fn ($r) => $r->where('name', 'super-admin'));

                if ($permissionExists) {
                    $q->orWhere(fn ($with) => $with->permission('consultations.manage'));
                }
            })
            ->get();
    }

    /** Tells the team. A failure here is reported but never undoes or fails what already happened. */
    private function notifyStaff(Notification $notification): void
    {
        try {
            NotificationFacade::send($this->staff(), $notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
