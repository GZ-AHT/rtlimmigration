<?php

use App\Actions\Consultations\ConsultationBookings;
use App\Enums\ConsultationStatus;
use App\Enums\Role;
use App\Mail\ConsultationMail;
use App\Mail\OtpMail;
use App\Models\ConsultationBooking;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ConsultationBooked;
use App\Notifications\ConsultationCancelled;
use App\Notifications\ConsultationRescheduleRequested;
use App\Support\Auth\Otp;
use App\Support\Consultations\AvailableSlots;
use App\Support\Consultations\ConsultationSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tuesday 1 Dec 2026, 03:00 UTC (09:00 in Dhaka).
    CarbonImmutable::setTestNow('2026-12-01 03:00:00 UTC');
    Mail::fake();
    Notification::fake();

    app(ConsultationSchedule::class)->save([
        'enabled' => true, 'timezone' => 'Asia/Dhaka', 'duration' => 30, 'buffer' => 0, 'min_notice_hours' => 0, 'max_days_ahead' => 30, 'auto_confirm' => false,
        'weekly' => collect(array_keys(ConsultationSchedule::DAYS))->mapWithKeys(fn ($d) => [$d => ['enabled' => true, 'start' => '10:00', 'end' => '12:00']])->all(),
        'breaks' => [], 'blocked' => [],
    ]);

    $this->admin = $this->userWithRole(Role::SuperAdmin);
    $this->manager = $this->userWithRole(Role::LeadManager);
    $this->counselor = $this->userWithRole(Role::Counselor);
    Service::create(['name' => 'Student visa', 'slug' => 'student-visa', 'is_active' => true, 'sort_order' => 1]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** 10:00 Dhaka on 2 Dec is 04:00 UTC; each following slot is 30 minutes later. */
/** @param  array<string, mixed>  $over */
function cbLead(array $over = []): Lead
{
    $email = $over['email'] ?? 'lead@example.com';

    return Lead::create(['lead_number' => 'LD-'.fake()->unique()->numerify('######'), 'name' => 'Lead Person', 'phone' => '01812345678', 'source' => 'website', 'email_normalized' => $email, ...$over, 'email' => $email]);
}

function cbSlot(int $n = 0, string $day = '2026-12-02'): string
{
    return CarbonImmutable::parse("{$day} 04:00:00 UTC")->addMinutes(30 * $n)->format('Y-m-d\TH:i:s\Z');
}

function cbVerified($test, string $email = 'rafi@example.com')
{
    return $test->withSession(['consultation.verified' => ['email' => $email, 'at' => time()]]);
}

function cbPayload(array $over = []): array
{
    return ['name' => 'Rafi Ahmed', 'phone' => '01712345678', 'country' => 'BD', 'subject' => 'Student visa', 'message' => 'I want to study in Canada.', 'starts_at' => cbSlot(0), 'timezone' => 'Asia/Dhaka', ...$over];
}

function cbBook($test, array $over = [], string $email = 'rafi@example.com')
{
    return cbVerified($test, $email)->postJson(route('consultation.book'), cbPayload($over));
}

/* ------------------------------------------------------------ email OTP */

it('emails a code to any address and verifies it, with a limit on wrong guesses', function () {
    $this->postJson(route('consultation.code'), ['email' => 'new.person@example.com'])->assertOk()->assertJsonPath('sent', true);
    Mail::assertQueued(OtpMail::class, fn (OtpMail $m) => $m->hasTo('new.person@example.com') && $m->purpose === Otp::BOOKING);

    $this->postJson(route('consultation.code'), ['email' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('email');
    // A second code too soon is refused quietly, not sent.
    $this->postJson(route('consultation.code'), ['email' => 'new.person@example.com'])->assertOk()->assertJsonPath('sent', false);
    Mail::assertQueuedCount(1);

    $code = null;
    Mail::assertQueued(OtpMail::class, function (OtpMail $m) use (&$code) {
        $code = $m->code;

        return true;
    });

    foreach (range(1, 5) as $i) {
        $this->postJson(route('consultation.verify'), ['email' => 'new.person@example.com', 'code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
    }
    // Locked: even the right code no longer works until a new one is requested.
    $this->postJson(route('consultation.verify'), ['email' => 'new.person@example.com', 'code' => $code])->assertUnprocessable();
});

it('verifies the right code and prefills what we already know about the customer', function () {
    $user = User::factory()->create(['name' => 'Known Student', 'email' => 'known@example.com']);
    cbLead(['email' => 'lead@example.com']);

    foreach (['known@example.com' => 'Known Student', 'lead@example.com' => 'Lead Person', 'nobody@example.com' => null] as $email => $name) {
        $this->postJson(route('consultation.code'), ['email' => $email])->assertOk();
        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $m) use ($email, &$code) {
            if ($m->hasTo($email)) {
                $code = $m->code;
            }

            return true;
        });

        $this->postJson(route('consultation.verify'), ['email' => $email, 'code' => $code])->assertOk()
            ->assertJsonPath('name', $name)->assertJsonPath('email', $email)->assertJsonPath('country', 'BD')->assertJsonPath('subjects.0', 'Student visa');
    }

    // No duplicate account is created for any of them.
    expect(User::where('email', 'lead@example.com')->exists())->toBeFalse()->and(User::where('email', 'known@example.com')->count())->toBe(1);
    expect($user->exists)->toBeTrue();
});

it('needs a verified email for the calendar and for booking', function () {
    $this->getJson(route('consultation.availability', ['from' => cbSlot(0), 'to' => cbSlot(8)]))->assertUnprocessable();
    $this->postJson(route('consultation.book'), cbPayload())->assertUnprocessable()->assertJsonValidationErrors('email');

    // The verification lapses after 30 minutes.
    $this->withSession(['consultation.verified' => ['email' => 'rafi@example.com', 'at' => time() - 31 * 60]])->postJson(route('consultation.book'), cbPayload())->assertUnprocessable();
});

/* ------------------------------------------------------------ availability */

it('offers only the times the admin schedule allows', function () {
    $slots = fn (array $q = []) => cbVerified($this)->getJson(route('consultation.availability', ['from' => '2026-12-02T00:00:00Z', 'to' => '2026-12-03T00:00:00Z', ...$q]))->assertOk()->json('slots');

    // 10:00-12:00 Dhaka in 30-minute slots: four on the 2nd.
    expect($slots())->toBe([cbSlot(0), cbSlot(1), cbSlot(2), cbSlot(3)]);

    // Today's earlier times are gone (it is 09:00 in Dhaka, minimum notice 0): nothing before "now".
    expect($slots(['from' => '2026-12-01T00:00:00Z', 'to' => '2026-12-02T00:00:00Z']))->not->toContain('2026-12-01T03:00:00Z');

    $schedule = app(ConsultationSchedule::class);
    $schedule->save(['breaks' => [['start' => '11:00', 'end' => '11:30']], 'blocked' => [['date' => '2026-12-03', 'from' => null, 'to' => null, 'reason' => 'Holiday']]]);
    expect($slots())->toBe([cbSlot(0), cbSlot(1), cbSlot(3)]);
    expect($slots(['from' => '2026-12-03T00:00:00Z', 'to' => '2026-12-04T00:00:00Z']))->toBe([]);

    $schedule->save(['blocked' => [['date' => '2026-12-02', 'from' => '10:00', 'to' => '11:00', 'reason' => 'Meeting']], 'breaks' => []]);
    expect($slots())->toBe([cbSlot(2), cbSlot(3)]);
});

it('honours days off, notice, the booking window, buffers and a switched-off schedule', function () {
    $schedule = app(ConsultationSchedule::class);
    $between = fn (string $from, string $to) => array_map(fn ($s) => $s->format('Y-m-d\TH:i:s\Z'), app(AvailableSlots::class)->between(CarbonImmutable::parse($from), CarbonImmutable::parse($to)));

    // Wednesday off.
    $weekly = $schedule->weekly();
    $weekly['wed']['enabled'] = false;
    $schedule->save(['weekly' => $weekly]);
    expect($between('2026-12-02T00:00:00Z', '2026-12-03T00:00:00Z'))->toBe([]);
    $schedule->save(['weekly' => [...$weekly, 'wed' => [...$weekly['wed'], 'enabled' => true]]]);

    // A buffer spaces slots: 30 + 15 minutes apart.
    $schedule->save(['buffer' => 15]);
    expect($between('2026-12-02T00:00:00Z', '2026-12-03T00:00:00Z'))->toBe([cbSlot(0), '2026-12-02T04:45:00Z', '2026-12-02T05:30:00Z']);
    $schedule->save(['buffer' => 0]);

    // Minimum notice: 36 hours from 03:00 UTC on the 1st is 15:00 UTC on the 2nd, so the 2nd is gone.
    $schedule->save(['min_notice_hours' => 36]);
    expect($between('2026-12-02T00:00:00Z', '2026-12-03T00:00:00Z'))->toBe([]);
    $schedule->save(['min_notice_hours' => 0]);

    // Booking window: only 1 day ahead.
    $schedule->save(['max_days_ahead' => 1]);
    expect($between('2026-12-02T00:00:00Z', '2026-12-05T00:00:00Z'))->toBe([cbSlot(0), cbSlot(1), cbSlot(2), cbSlot(3)])
        ->and($between('2026-12-03T00:00:00Z', '2026-12-05T00:00:00Z'))->toBe([]);
    $schedule->save(['max_days_ahead' => 30]);

    $schedule->save(['enabled' => false]);
    cbVerified($this)->getJson(route('consultation.availability', ['from' => '2026-12-02T00:00:00Z', 'to' => '2026-12-03T00:00:00Z']))->assertNotFound();
    $this->postJson(route('consultation.code'), ['email' => 'a@example.com'])->assertNotFound();
});

/* ------------------------------------------------------------ booking */

it('books a verified customer as a pending request, with a reference, UTC times and emails', function () {
    $response = cbBook($this)->assertCreated()
        ->assertJsonPath('booking.status', 'pending')->assertJsonPath('booking.type', 'Video Consultation')
        ->assertJsonPath('booking.duration_minutes', 30)->assertJsonPath('booking.timezone', 'Asia/Dhaka')
        ->assertJsonPath('booking.starts_at', cbSlot(0))->assertJsonPath('booking.email', 'rafi@example.com');

    $booking = ConsultationBooking::sole();
    expect($booking->reference)->toMatch('/^CB-[A-Z0-9]{6}$/')->and($response->json('booking.reference'))->toBe($booking->reference)
        ->and($booking->status)->toBe(ConsultationStatus::Pending)->and($booking->phone)->toBe('+8801712345678')
        ->and($booking->meeting_url)->toBeNull()->and($booking->events)->toHaveCount(1)
        // Stored as UTC whatever the visitor's timezone.
        ->and(DB::table('consultation_bookings')->value('starts_at'))->toBe('2026-12-02 04:00:00');

    // Never approved on its own, and the customer sees nothing admin-only.
    expect($response->json('booking'))->not->toHaveKeys(['admin_notes', 'events', 'user_id', 'lead_id'])->and($response->json('booking.meeting_url'))->toBeNull();
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->hasTo('rafi@example.com') && $m->kind === 'received');
    Notification::assertSentTo($this->admin, ConsultationBooked::class);
    Notification::assertSentTo($this->manager, ConsultationBooked::class);
    Notification::assertNotSentTo($this->counselor, ConsultationBooked::class);

    // The email always lists the details, in the customer's timezone.
    $html = (new ConsultationMail($booking, 'received'))->render();
    expect($html)->toContain($booking->reference)->toContain('Asia/Dhaka')->toContain('10:00 AM')->toContain('30 minutes')->toContain('Video consultation');
});

it('links an existing customer instead of creating a new account', function () {
    $user = User::factory()->create(['email' => 'rafi@example.com']);
    $lead = cbLead(['email' => 'rafi@example.com']);

    cbBook($this)->assertCreated();

    expect(ConsultationBooking::sole())->user_id->toBe($user->id)->lead_id->toBe($lead->id)
        ->and(User::where('email', 'rafi@example.com')->count())->toBe(1);
});

it('confirms at once only when the admin allows instant confirmation', function () {
    app(ConsultationSchedule::class)->save(['auto_confirm' => true]);

    cbBook($this)->assertCreated()->assertJsonPath('booking.status', 'confirmed');
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'confirmed');
});

it('refuses a time that is taken, in the past, off the schedule, or invalid', function () {
    // Many rapid requests in one test: the rate limit is checked separately.
    $this->withoutMiddleware(ThrottleRequests::class);
    cbBook($this)->assertCreated();

    // The same slot again, by someone else.
    cbBook($this, ['name' => 'Someone Else'], 'other@example.com')->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    expect(ConsultationBooking::count())->toBe(1);

    // Not on the schedule: 10:07, a day off the grid, outside hours, in the past.
    foreach (['2026-12-02T04:07:00Z', '2026-12-02T08:00:00Z', '2026-12-01T01:00:00Z', '2027-03-01T04:00:00Z'] as $time) {
        cbBook($this, ['starts_at' => $time], 'third@example.com')->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    }

    cbBook($this, ['starts_at' => 'not a date'], 'third@example.com')->assertUnprocessable();
    cbBook($this, ['phone' => '123', 'starts_at' => cbSlot(1)], 'third@example.com')->assertUnprocessable()->assertJsonValidationErrors('phone');
    cbBook($this, ['subject' => 'Made up', 'starts_at' => cbSlot(1)], 'third@example.com')->assertUnprocessable()->assertJsonValidationErrors('subject');
    cbBook($this, ['timezone' => 'Mars/Base', 'starts_at' => cbSlot(1)], 'third@example.com')->assertUnprocessable()->assertJsonValidationErrors('timezone');

    // A booking is for the verified email only: one in the body is ignored.
    cbBook($this, ['email' => 'forged@example.com', 'starts_at' => cbSlot(1)], 'third@example.com')->assertCreated();
    expect(ConsultationBooking::where('email', 'forged@example.com')->exists())->toBeFalse()->and(ConsultationBooking::where('email', 'third@example.com')->exists())->toBeTrue();
});

it('lets the database itself refuse a second active booking for one start time', function () {
    cbBook($this)->assertCreated();
    $first = ConsultationBooking::sole();

    // Even if the application check were skipped, the unique index stops it.
    expect(fn () => ConsultationBooking::create([
        'reference' => 'CB-TESTXX', 'name' => 'X', 'email' => 'x@example.com', 'phone' => '+8801712345678', 'subject' => 'S', 'timezone' => 'UTC',
        'starts_at' => $first->starts_at, 'ends_at' => $first->ends_at, 'duration_minutes' => 30, 'status' => 'pending', 'active_slot' => $first->starts_at,
    ]))->toThrow(UniqueConstraintViolationException::class);

    // A cancelled booking frees its time.
    app(ConsultationBookings::class)->cancel($first, $this->admin, null);
    expect($first->fresh()->active_slot)->toBeNull();
    cbBook($this, [], 'later@example.com')->assertCreated();
});

it('keeps a buffer between appointments', function () {
    app(ConsultationSchedule::class)->save(['buffer' => 15]);
    cbBook($this)->assertCreated();

    // 10:00-10:30 is booked, so 10:45 (the next slot on the grid) is free but nothing overlaps the buffer.
    $slots = cbVerified($this)->getJson(route('consultation.availability', ['from' => '2026-12-02T00:00:00Z', 'to' => '2026-12-03T00:00:00Z']))->json('slots');
    expect($slots)->toBe(['2026-12-02T04:45:00Z', '2026-12-02T05:30:00Z']);
});

/* ------------------------------------------------------------ admin */

function cbPending(?string $email = 'rafi@example.com', int $n = 0): ConsultationBooking
{
    test()->withSession(['consultation.verified' => ['email' => $email, 'at' => time()]])->postJson(route('consultation.book'), cbPayload(['starts_at' => cbSlot($n)]))->assertCreated();
    Mail::fake();
    Notification::fake();

    return ConsultationBooking::where('email', $email)->latest('id')->firstOrFail();
}

it('protects the admin screens with permissions', function () {
    $booking = cbPending();

    foreach ([route('admin.consultations.index'), route('admin.consultations.calendar'), route('admin.consultations.availability'), route('admin.consultations.show', $booking)] as $url) {
        $this->actingAs($this->counselor)->get($url)->assertForbidden();
    }

    $this->actingAs($this->counselor)->post(route('admin.consultations.approve', $booking))->assertForbidden();
    $this->actingAs($this->counselor)->putJson(route('admin.consultations.availability.update'), [])->assertForbidden();
    auth()->logout();
    $this->post(route('admin.consultations.approve', $booking))->assertRedirect(route('login'));

    foreach ([route('admin.consultations.index'), route('admin.consultations.calendar'), route('admin.consultations.availability')] as $url) {
        $this->actingAs($this->manager)->get($url)->assertOk();
    }
});

it('lists, filters and searches bookings with the customer details', function () {
    cbPending('anna@example.com', 0);
    $two = cbPending('bob@example.com', 1);
    app(ConsultationBookings::class)->approve($two, $this->admin);

    $this->actingAs($this->admin)->get(route('admin.consultations.index'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/consultations/Index')->has('bookings.data', 2)->where('counts.pending', 1)->where('counts.confirmed', 1)
            ->where('bookings.data.0.email', 'anna@example.com')->where('bookings.data.0.phone', '+8801712345678')->where('bookings.data.0.subject', 'Student visa'));

    $this->actingAs($this->admin)->get(route('admin.consultations.index', ['status' => 'confirmed']))->assertInertia(fn (Assert $p) => $p->has('bookings.data', 1)->where('bookings.data.0.email', 'bob@example.com'));
    $this->actingAs($this->admin)->get(route('admin.consultations.index', ['search' => 'anna']))->assertInertia(fn (Assert $p) => $p->has('bookings.data', 1));
    $this->actingAs($this->admin)->get(route('admin.consultations.index', ['search' => $two->reference]))->assertInertia(fn (Assert $p) => $p->has('bookings.data', 1)->where('bookings.data.0.email', 'bob@example.com'));
    $this->actingAs($this->admin)->get(route('admin.consultations.index', ['status' => 'bogus']))->assertSessionHasErrors('status');
});

it('approves a request, and sends the meeting link only to the verified email', function () {
    $booking = cbPending();

    $this->actingAs($this->admin)->post(route('admin.consultations.approve', $booking))->assertRedirect();
    expect($booking->fresh()->status)->toBe(ConsultationStatus::Confirmed);
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'confirmed' && $m->hasTo('rafi@example.com'));

    // No link yet: sending is refused rather than pretending.
    $this->actingAs($this->admin)->post(route('admin.consultations.meeting.send', $booking))->assertSessionHasErrors('meeting_url');

    // A Zoom link must really be on zoom.us, and links must be https.
    $this->actingAs($this->admin)->put(route('admin.consultations.meeting', $booking), ['meeting_provider' => 'zoom', 'meeting_url' => 'https://evil.example/j/123'])->assertSessionHasErrors('meeting_url');
    $this->actingAs($this->admin)->put(route('admin.consultations.meeting', $booking), ['meeting_provider' => 'google_meet', 'meeting_url' => 'https://zoom.us/j/123'])->assertSessionHasErrors('meeting_url');
    $this->actingAs($this->admin)->put(route('admin.consultations.meeting', $booking), ['meeting_provider' => 'zoom', 'meeting_url' => 'http://zoom.us/j/123'])->assertSessionHasErrors('meeting_url');

    $this->actingAs($this->admin)->put(route('admin.consultations.meeting', $booking), ['meeting_provider' => 'google_meet', 'meeting_url' => 'https://meet.google.com/abc-defg-hij', 'send' => true])->assertSessionHasNoErrors();
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'meeting' && $m->hasTo('rafi@example.com') && $m->booking->meeting_url === 'https://meet.google.com/abc-defg-hij');
    expect($booking->fresh()->meeting_sent_at)->not->toBeNull();

    $html = (new ConsultationMail($booking->fresh(), 'meeting'))->render();
    expect($html)->toContain('https://meet.google.com/abc-defg-hij')->toContain('Google Meet')->toContain('Join the video consultation');

    // Replace it and send again.
    $this->actingAs($this->admin)->put(route('admin.consultations.meeting', $booking), ['meeting_provider' => 'zoom', 'meeting_url' => 'https://us02web.zoom.us/j/999'])->assertSessionHasNoErrors();
    expect($booking->fresh()->meeting_url)->toBe('https://us02web.zoom.us/j/999')->and($booking->fresh()->meeting_sent_at)->toBeNull();
    $this->actingAs($this->admin)->post(route('admin.consultations.meeting.send', $booking))->assertRedirect();
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'meeting' && $m->booking->meeting_url === 'https://us02web.zoom.us/j/999');

    $types = $booking->fresh()->events->pluck('type')->all();
    expect($types)->toContain('approved', 'meeting_link', 'meeting_sent', 'meeting_resent');
});

it('approves with a meeting link in one step and cannot send a link before approval', function () {
    $booking = cbPending();

    $this->actingAs($this->admin)->put(route('admin.consultations.meeting', $booking), ['meeting_provider' => 'zoom', 'meeting_url' => 'https://zoom.us/j/1', 'send' => true])->assertSessionHasErrors('status');
    Mail::assertNothingQueued();

    $this->actingAs($this->admin)->post(route('admin.consultations.approve', $booking), ['meeting_provider' => 'zoom', 'meeting_url' => 'https://zoom.us/j/1'])->assertSessionHasNoErrors();
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'meeting');
    expect($booking->fresh())->status->toBe(ConsultationStatus::Confirmed)->meeting_provider->toBe('zoom');
});

it('rejects, cancels and completes, freeing the time when it should', function () {
    $a = cbPending('a@example.com', 0);
    $this->actingAs($this->admin)->post(route('admin.consultations.reject', $a), ['reason' => 'No advisers that day.'])->assertRedirect();
    expect($a->fresh()->status)->toBe(ConsultationStatus::Cancelled)->and($a->fresh()->active_slot)->toBeNull();
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'declined' && $m->extra['reason'] === 'No advisers that day.');
    // Rejected and cancelled can't be approved again.
    $this->actingAs($this->admin)->post(route('admin.consultations.approve', $a))->assertSessionHasErrors('status');
    // The time is free again.
    cbBook($this, [], 'again@example.com')->assertCreated();

    $b = cbPending('b@example.com', 1);
    app(ConsultationBookings::class)->approve($b, $this->admin);
    $this->actingAs($this->admin)->post(route('admin.consultations.cancel', $b), ['reason' => 'Adviser unwell.'])->assertRedirect();
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'cancelled' && $m->hasTo('b@example.com'));
    Notification::assertNotSentTo($this->admin, ConsultationCancelled::class);

    $c = cbPending('c@example.com', 2);
    app(ConsultationBookings::class)->approve($c, $this->admin);
    $this->actingAs($this->admin)->post(route('admin.consultations.complete', $c))->assertRedirect();
    expect($c->fresh()->status)->toBe(ConsultationStatus::Completed);
    $this->actingAs($this->admin)->post(route('admin.consultations.cancel', $c))->assertSessionHasErrors('status');
});

it('reschedules to a free time, never onto another appointment, and emails the customer', function () {
    $a = cbPending('a@example.com', 0);
    $b = cbPending('b@example.com', 1);
    $this->actingAs($this->admin)->post(route('admin.consultations.approve', $a));

    // Onto B's time: refused.
    $this->actingAs($this->admin)->post(route('admin.consultations.reschedule', $a), ['starts_at' => cbSlot(1)])->assertSessionHasErrors('starts_at');
    // Into the past: refused.
    $this->actingAs($this->admin)->post(route('admin.consultations.reschedule', $a), ['starts_at' => '2026-11-20T04:00:00Z'])->assertSessionHasErrors('starts_at');
    expect($a->fresh()->starts_at->format('c'))->toBe(CarbonImmutable::parse(cbSlot(0))->format('c'));

    $this->actingAs($this->admin)->post(route('admin.consultations.reschedule', $a), ['starts_at' => cbSlot(0, '2026-12-03'), 'note' => 'Customer asked.'])->assertSessionHasNoErrors();
    expect($a->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-12-03 04:00')->and($a->fresh()->active_slot->format('Y-m-d H:i'))->toBe('2026-12-03 04:00');
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'rescheduled' && $m->hasTo('a@example.com') && str_contains((string) ($m->extra['previous'] ?? ''), '10:00 AM'));
    expect($a->fresh()->events->last())->type->toBe('rescheduled')->note->toBe('Customer asked.');

    // Its old time is free again; B keeps theirs.
    cbBook($this, [], 'new@example.com')->assertCreated();
    expect($b->fresh()->starts_at->format('H:i'))->toBe('04:30');
});

it('keeps internal notes and the full history, visible only to staff', function () {
    $booking = cbPending();
    $this->actingAs($this->admin)->post(route('admin.consultations.notes.store', $booking), ['note' => 'Call back about fees.'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('admin.consultations.notes.store', $booking), ['note' => ''])->assertSessionHasErrors('note');
    app(ConsultationBookings::class)->approve($booking, $this->admin);

    $this->actingAs($this->admin)->get(route('admin.consultations.show', $booking))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/consultations/Show')->where('booking.events.0.type', 'created')
            ->where('booking.events.1.type', 'note')->where('booking.events.1.note', 'Call back about fees.')->where('booking.events.1.by', $this->admin->name)
            ->where('booking.events.2.type', 'approved')->has('suggested')->where('businessTimezone', 'Asia/Dhaka'));
});

it('shows bookings on the calendar by month, week and day', function () {
    cbPending('a@example.com', 0);
    cbPending('b@example.com', 2);
    $count = fn (string $view, string $date) => $this->actingAs($this->admin)->get(route('admin.consultations.calendar', ['view' => $view, 'date' => $date]))->assertOk()->viewData('page')['props']['bookings'];

    expect($count('month', '2026-12-15'))->toHaveCount(2)->and($count('week', '2026-12-02'))->toHaveCount(2)->and($count('day', '2026-12-02'))->toHaveCount(2)
        ->and($count('day', '2026-12-03'))->toHaveCount(0)->and($count('month', '2027-02-01'))->toHaveCount(0);
});

it('lets staff set the availability, validates it, and customers see it at once', function () {
    $payload = fn (array $over = []) => [...app(ConsultationSchedule::class)->forAdmin(), ...$over];

    $this->actingAs($this->admin)->get(route('admin.consultations.availability'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/consultations/Availability')->where('schedule.duration', 30)->has('timezones'));

    $weekly = $payload()['weekly'];
    $weekly['wed'] = ['enabled' => true, 'start' => '14:00', 'end' => '15:00'];
    $this->actingAs($this->admin)->put(route('admin.consultations.availability.update'), $payload(['weekly' => $weekly, 'duration' => 60, 'buffer' => 0]))->assertSessionHasNoErrors();

    $slots = cbVerified($this)->getJson(route('consultation.availability', ['from' => '2026-12-02T00:00:00Z', 'to' => '2026-12-03T00:00:00Z']))->json('slots');
    // Wednesday 14:00-15:00 Dhaka = 08:00 UTC, one 60-minute slot.
    expect($slots)->toBe(['2026-12-02T08:00:00Z']);

    foreach ([
        ['duration' => 20], ['buffer' => 500], ['timezone' => 'Mars/Base'], ['max_days_ahead' => 0],
        ['weekly' => [...$weekly, 'mon' => ['enabled' => true, 'start' => '18:00', 'end' => '09:00']]],
        ['weekly' => [...$weekly, 'mon' => ['enabled' => true, 'start' => '9am', 'end' => '17:00']]],
        ['breaks' => [['start' => '13:00', 'end' => '12:00']]],
        ['blocked' => [['date' => 'tomorrow', 'from' => null, 'to' => null, 'reason' => '']]],
        ['blocked' => [['date' => '2026-12-05', 'from' => '10:00', 'to' => null, 'reason' => '']]],
    ] as $bad) {
        $this->actingAs($this->admin)->putJson(route('admin.consultations.availability.update'), $payload($bad))->assertUnprocessable();
    }
});

/* ------------------------------------------------------------ customer manage page, reminders */

it('lets a customer cancel or ask for a new time from a signed link only', function () {
    $booking = cbPending();
    $signed = URL::signedRoute('consultation.manage', ['reference' => $booking->reference]);

    $this->get(route('consultation.manage', $booking->reference))->assertForbidden();
    $this->post(route('consultation.manage.cancel', $booking->reference))->assertForbidden();

    $this->get($signed)->assertOk()->assertInertia(fn (Assert $p) => $p->component('website/ConsultationManage')->where('booking.reference', $booking->reference)
        ->where('canChange', true)->missing('booking.admin_notes')->has('urls.cancel'));

    $url = fn (string $name) => URL::signedRoute($name, ['reference' => $booking->reference]);

    $this->post($url('consultation.manage.reschedule'), ['request' => ''])->assertSessionHasErrors('request');
    $this->post($url('consultation.manage.reschedule'), ['request' => 'Could we do Thursday afternoon instead?'])->assertSessionHasNoErrors();
    Notification::assertSentTo($this->admin, ConsultationRescheduleRequested::class);
    expect($booking->fresh()->events->last())->type->toBe('reschedule_requested');

    $this->post($url('consultation.manage.cancel'))->assertRedirect();
    expect($booking->fresh())->status->toBe(ConsultationStatus::Cancelled)->active_slot->toBeNull();
    Notification::assertSentTo($this->admin, ConsultationCancelled::class);
    Notification::assertSentTo($this->manager, ConsultationCancelled::class);

    $this->get($signed)->assertInertia(fn (Assert $p) => $p->where('canChange', false)->where('booking.status', 'cancelled'));
    // A tampered reference is not accepted.
    $this->post(str_replace($booking->reference, 'CB-AAAAAA', $url('consultation.manage.cancel')))->assertForbidden();
});

it('sends the reminder once, only for confirmed appointments within a day', function () {
    $soon = cbPending('soon@example.com', 0);
    $later = cbPending('later@example.com', 1);
    $unconfirmed = cbPending('pending@example.com', 2);
    app(ConsultationBookings::class)->approve($soon, $this->admin);
    app(ConsultationBookings::class)->approve($later, $this->admin);
    Mail::fake();

    // 03:00 UTC on the 1st: the 2nd's 04:00 UTC slot is 25 hours away, so nothing yet.
    expect(app(ConsultationBookings::class)->sendReminders())->toBe(0);

    CarbonImmutable::setTestNow('2026-12-01 05:00:00 UTC');
    expect(app(ConsultationBookings::class)->sendReminders())->toBe(2);
    Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->kind === 'reminder' && $m->hasTo('soon@example.com'));
    Mail::assertNotQueued(ConsultationMail::class, fn (ConsultationMail $m) => $m->hasTo('pending@example.com'));

    // Not again.
    expect(app(ConsultationBookings::class)->sendReminders())->toBe(0);
    expect($unconfirmed->fresh()->reminder_sent_at)->toBeNull();
});

it('rate limits booking attempts', function () {
    foreach (range(1, 10) as $i) {
        cbBook($this, ['starts_at' => '2026-12-02T08:00:00Z'], "p{$i}@example.com")->assertUnprocessable();
    }

    cbBook($this, ['starts_at' => '2026-12-02T08:00:00Z'], 'p11@example.com')->assertStatus(429);
});
