<?php

namespace App\Http\Controllers\Admin\Consultations;

use App\Actions\Consultations\ConsultationBookings;
use App\Enums\ConsultationStatus;
use App\Http\Controllers\Controller;
use App\Models\ConsultationBooking;
use App\Models\User;
use App\Support\Consultations\AvailableSlots;
use App\Support\Consultations\ConsultationSchedule;
use App\Support\Consultations\MeetingLinkRules;
use App\Support\CounselorDirectory;
use App\Support\Presenters\ConsultationPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Video consultation bookings: list, detail, approve, reschedule, cancel, notes, meeting links. */
class ConsultationController extends Controller
{
    public function __construct(private ConsultationBookings $bookings) {}

    public function index(Request $request): Response
    {
        Gate::authorize('consultations.view');

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_column(ConsultationStatus::cases(), 'value'))],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $rows = ConsultationBooking::query()->with('counselor:id,name')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, function ($q, string $s) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s).'%';
                $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('reference', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->orderByRaw("case status when 'pending' then 0 when 'confirmed' then 1 else 2 end")
            ->orderBy('starts_at')
            ->paginate(20)->withQueryString();

        $counts = ConsultationBooking::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('admin/consultations/Index', [
            'bookings' => $rows->through(fn (ConsultationBooking $b) => ConsultationPresenter::row($b)),
            'filters' => ['status' => $filters['status'] ?? '', 'search' => $filters['search'] ?? ''],
            'counts' => collect(ConsultationStatus::cases())->mapWithKeys(fn (ConsultationStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all(),
            'can' => ['manage' => $request->user()->can('consultations.manage')],
        ]);
    }

    public function show(Request $request, ConsultationBooking $booking, AvailableSlots $slots, ConsultationSchedule $schedule, CounselorDirectory $counselors): Response
    {
        Gate::authorize('consultations.view');

        $upcoming = $slots->between(CarbonImmutable::now('UTC'), CarbonImmutable::now('UTC')->addDays(21), $booking->id);

        return Inertia::render('admin/consultations/Show', [
            'booking' => ConsultationPresenter::detail($booking),
            'suggested' => array_map(fn (CarbonImmutable $s) => $s->format('Y-m-d\TH:i:s\Z'), array_slice($upcoming, 0, 60)),
            'businessTimezone' => $schedule->timezone(),
            'providers' => ConsultationBooking::PROVIDERS,
            'counselors' => $request->user()->can('consultations.manage')
                ? array_map(fn (array $o) => ['value' => $o['value'], 'label' => $o['label']], $counselors->options())
                : [],
            'can' => ['manage' => $request->user()->can('consultations.manage')],
        ]);
    }

    public function approve(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $data = MeetingLinkRules::validate($request, required: false);

        $emailed = $this->bookings->approve($booking, $request->user(), $data['meeting_provider'] ?? null, $data['meeting_url'] ?? null);

        if (! $emailed) {
            $this->toast(['type' => 'warning', 'message' => __('Booking approved, but the confirmation email could not be sent. Check the email settings, then use "Resend invitation" or contact the customer.')]);

            return back();
        }

        return $this->done($booking->meeting_url !== null
            ? __('Booking approved. The customer was emailed the confirmation with the meeting link.')
            : __('Booking approved and the customer was emailed. There is no meeting link yet: add one and send it.'));
    }

    public function counselor(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $data = $request->validate(['counselor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')]]);
        $counselor = isset($data['counselor_id']) ? User::query()->whereKey($data['counselor_id'])->firstOrFail() : null;

        $this->bookings->assignCounselor($booking, $request->user(), $counselor);

        return $this->done($counselor ? __('Counselor assigned.') : __('Counselor removed.'));
    }

    public function reject(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->bookings->reject($booking, $request->user(), $data['reason'] ?? null);

        return $this->done(__('Request rejected and the customer was told.'));
    }

    public function cancel(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->bookings->cancel($booking, $request->user(), $data['reason'] ?? null);

        return $this->done(__('Appointment cancelled and the customer was told.'));
    }

    public function complete(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $this->bookings->complete($booking, $request->user());

        return $this->done(__('Marked as completed.'));
    }

    public function reschedule(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $data = $request->validate(['starts_at' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:500']]);

        $this->bookings->reschedule($booking, $request->user(), CarbonImmutable::parse($data['starts_at']), $data['note'] ?? null);

        return $this->done(__('Appointment rescheduled and the customer was emailed.'));
    }

    /** Saves (or replaces) the meeting link; "send" also emails it. */
    public function meeting(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $data = MeetingLinkRules::validate($request, required: true);

        $this->bookings->setMeeting($booking, $request->user(), $data['meeting_provider'], $data['meeting_url']);

        if ($request->boolean('send')) {
            return $this->done($this->bookings->sendMeeting($booking->refresh(), $request->user(), resend: false)
                ? __('Meeting link saved and emailed to the customer.')
                : __('This link was already emailed to the customer. Use "Resend invitation" to send it again.'));
        }

        return $this->done(__('Meeting link saved.'));
    }

    public function sendMeeting(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $this->bookings->sendMeeting($booking, $request->user());

        return $this->done(__('Meeting invitation sent again.'));
    }

    public function note(Request $request, ConsultationBooking $booking): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $this->bookings->note($booking, $request->user(), $data['note']);

        return $this->done(__('Note added.'));
    }

    private function done(string $message): RedirectResponse
    {
        $this->toast(['type' => 'success', 'message' => $message]);

        return back();
    }
}
