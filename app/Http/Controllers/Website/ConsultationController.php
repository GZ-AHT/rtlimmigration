<?php

namespace App\Http\Controllers\Website;

use App\Actions\Consultations\ConsultationBookings;
use App\Enums\ConsultationStatus;
use App\Http\Controllers\Controller;
use App\Models\ConsultationBooking;
use App\Models\Service;
use App\Support\Auth\Otp;
use App\Support\Consultations\AvailableSlots;
use App\Support\Consultations\ConsultationSchedule;
use App\Support\Consultations\CustomerLookup;
use App\Support\Phone\PhoneNumbers;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public video consultation booking (the popup on the website): verify an
 * email with a code, fill in the details, pick an available time, confirm.
 * The verified email lives in the visitor's session for 30 minutes and is the
 * only email a booking can be made for. What is bookable always comes from the
 * admin schedule (AvailableSlots), never from the browser.
 */
class ConsultationController extends Controller
{
    private const SESSION = 'consultation.verified';

    private const VERIFIED_MINUTES = 30;

    private const OTHER_SUBJECT = 'Other';

    public function __construct(
        private ConsultationSchedule $schedule,
        private AvailableSlots $slots,
        private ConsultationBookings $bookings,
        private Otp $otp,
    ) {}

    /** Step 1: email a verification code. The answer is the same for every address. */
    public function sendCode(Request $request): JsonResponse
    {
        $this->ensureOpen();
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']], ['email.required' => __('Enter your email address.')]);

        $wait = $this->otp->send($data['email'], Otp::BOOKING, null, '');

        return response()->json(['sent' => $wait === 0, 'resend_in' => $wait > 0 ? $wait : Otp::COOLDOWN_SECONDS]);
    }

    /** Step 1b: check the code. On success the visitor gets what we need for the form. */
    public function verifyCode(Request $request): JsonResponse
    {
        $this->ensureOpen();
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255'], 'code' => ['required', 'string', 'max:12']]);
        $email = Str::lower($data['email']);

        $result = $this->otp->verify($email, Otp::BOOKING, $data['code']);

        if ($result !== Otp::RESULT_OK) {
            throw ValidationException::withMessages(['code' => Otp::message($result)]);
        }

        $request->session()->put(self::SESSION, ['email' => $email, 'at' => time()]);

        $known = CustomerLookup::forEmail($email);
        $phone = $known['phone'] ? PhoneNumbers::normalize($known['phone']) : null;

        return response()->json([
            'email' => $email,
            'name' => $known['name'],
            'phone' => $phone,
            'country' => $phone ? (PhoneNumbers::regionOf($phone) ?? PhoneNumbers::DEFAULT_COUNTRY) : PhoneNumbers::DEFAULT_COUNTRY,
            'subjects' => [...Service::query()->where('is_active', true)->orderBy('name')->pluck('name')->all(), self::OTHER_SUBJECT],
            'countries' => PhoneNumbers::countries(),
            'duration' => $this->schedule->duration(),
        ]);
    }

    /** Step 3: every free start time in a range (UTC). The browser groups them by its own dates. */
    public function availability(Request $request): JsonResponse
    {
        $this->ensureOpen();
        $this->verifiedEmail($request);

        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after:from']]);
        $from = CarbonImmutable::parse($data['from'])->utc();
        $to = CarbonImmutable::parse($data['to'])->utc();

        if ($from->diffInDays($to) > 70) {
            throw ValidationException::withMessages(['to' => __('Choose a shorter range.')]);
        }

        return response()->json([
            'slots' => array_map(fn (CarbonImmutable $s) => $s->format('Y-m-d\TH:i:s\Z'), $this->slots->between($from, $to)),
            'duration' => $this->schedule->duration(),
            'max_days_ahead' => $this->schedule->maxDaysAhead(),
        ]);
    }

    /** Step 4: save the booking. */
    public function book(Request $request): JsonResponse
    {
        $this->ensureOpen();
        $email = $this->verifiedEmail($request);

        $subjects = [...Service::query()->where('is_active', true)->pluck('name')->all(), self::OTHER_SUBJECT];
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'size:2'],
            'subject' => ['required', 'string', Rule::in($subjects)],
            'message' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ], [
            'subject.in' => __('Choose a consultation subject.'),
            'phone.required' => __('Enter your phone number.'),
        ]);

        $country = PhoneNumbers::isCountry((string) ($data['country'] ?? '')) ? strtoupper((string) $data['country']) : PhoneNumbers::DEFAULT_COUNTRY;
        $phone = PhoneNumbers::normalize($data['phone'], $country)
            ?? throw ValidationException::withMessages(['phone' => __('Enter a valid phone number.')]);

        $known = CustomerLookup::forEmail($email);

        $booking = $this->bookings->book([
            'name' => trim($data['name']),
            'email' => $email,
            'phone' => $phone,
            'subject' => $data['subject'],
            'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
            'timezone' => $data['timezone'],
        ], CarbonImmutable::parse($data['starts_at']), ['user_id' => $known['user_id'], 'lead_id' => $known['lead_id']]);

        // One verification, one booking.
        $request->session()->forget(self::SESSION);

        return response()->json(['booking' => $this->summary($booking)], 201);
    }

    /** The page behind the "Manage this appointment" link in the emails (a signed address). */
    public function manage(Request $request, string $reference): Response
    {
        $booking = ConsultationBooking::query()->where('reference', $reference)->firstOrFail();

        return Inertia::render('website/ConsultationManage', [
            'booking' => $this->summary($booking),
            'canChange' => in_array($booking->status, [ConsultationStatus::Pending, ConsultationStatus::Confirmed], true) && $booking->starts_at->isFuture(),
            'urls' => [
                'cancel' => URL::signedRoute('consultation.manage.cancel', ['reference' => $reference]),
                'reschedule' => URL::signedRoute('consultation.manage.reschedule', ['reference' => $reference]),
            ],
        ]);
    }

    public function cancel(string $reference): RedirectResponse
    {
        $booking = ConsultationBooking::query()->where('reference', $reference)->firstOrFail();
        $this->bookings->cancel($booking, null, __('Cancelled by the customer.'));
        $this->toast(['type' => 'success', 'message' => __('Your appointment was cancelled.')]);

        return back();
    }

    public function reschedule(Request $request, string $reference): RedirectResponse
    {
        $booking = ConsultationBooking::query()->where('reference', $reference)->firstOrFail();
        $data = $request->validate(['request' => ['required', 'string', 'min:5', 'max:1000']], ['request.required' => __('Tell us which time suits you.')]);

        $this->bookings->requestReschedule($booking, $data['request']);
        $this->toast(['type' => 'success', 'message' => __('Thanks. Our team will email you about a new time.')]);

        return back();
    }

    /** @return array<string, mixed> only what the customer may see */
    private function summary(ConsultationBooking $b): array
    {
        return [
            'reference' => $b->reference,
            'name' => $b->name,
            'email' => $b->email,
            'subject' => $b->subject,
            'starts_at' => $b->starts_at->format('Y-m-d\TH:i:s\Z'),
            'ends_at' => $b->ends_at->format('Y-m-d\TH:i:s\Z'),
            'duration_minutes' => $b->duration_minutes,
            'timezone' => $b->timezone,
            'status' => $b->status->value,
            'status_label' => $b->status->label(),
            'type' => 'Video Consultation',
            'meeting_url' => $b->status === ConsultationStatus::Confirmed ? $b->meeting_url : null,
        ];
    }

    private function ensureOpen(): void
    {
        abort_unless($this->schedule->enabled(), 404);
    }

    private function verifiedEmail(Request $request): string
    {
        $verified = $request->session()->get(self::SESSION);

        if (! is_array($verified) || ! is_string($verified['email'] ?? null) || time() - (int) ($verified['at'] ?? 0) > self::VERIFIED_MINUTES * 60) {
            throw ValidationException::withMessages(['email' => __('Please verify your email address first.')]);
        }

        return $verified['email'];
    }
}
