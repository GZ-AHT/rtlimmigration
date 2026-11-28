<?php

namespace App\Http\Controllers\Admin\Consultations;

use App\Actions\Consultations\ConsultationBookings;
use App\Http\Controllers\Controller;
use App\Models\ConsultationBooking;
use App\Support\Consultations\ConsultationSchedule;
use App\Support\Consultations\MeetingLinkRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

/** The schedule customers can book from: days, hours, breaks, blocked times and booking limits. */
class AvailabilityController extends Controller
{
    private const TIME = ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'];

    public function edit(ConsultationSchedule $schedule): Response
    {
        Gate::authorize('consultations.manage');

        return Inertia::render('admin/consultations/Availability', [
            'schedule' => $schedule->forAdmin(),
            'defaultMeeting' => $schedule->defaultMeeting(),
            'providers' => ConsultationBooking::PROVIDERS,
            'days' => ConsultationSchedule::DAYS,
            'durations' => ConsultationSchedule::DURATIONS,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    /** The link every video consultation starts with. An empty link removes it. */
    public function updateMeeting(Request $request, ConsultationBookings $bookings): RedirectResponse
    {
        Gate::authorize('consultations.manage');
        $request->validate(['meeting_provider' => ['required']]);
        $data = MeetingLinkRules::validate($request, required: false);

        $bookings->saveDefaultMeeting($request->user(), $data['meeting_provider'], $data['meeting_url'] ?? null);

        activity('consultations')->causedBy($request->user())->log('Default consultation meeting link updated');

        $this->toast(['type' => 'success', 'message' => isset($data['meeting_url']) ? __('Default meeting link saved.') : __('Default meeting link removed.')]);

        return back();
    }

    public function update(Request $request, ConsultationSchedule $schedule): RedirectResponse
    {
        Gate::authorize('consultations.manage');

        $dayKeys = array_keys(ConsultationSchedule::DAYS);

        $validator = validator($request->all(), [
            'enabled' => ['required', 'boolean'],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'duration' => ['required', 'integer', Rule::in(ConsultationSchedule::DURATIONS)],
            'buffer' => ['required', 'integer', 'between:0,120'],
            'min_notice_hours' => ['required', 'integer', 'between:0,720'],
            'max_days_ahead' => ['required', 'integer', 'between:1,365'],
            'auto_confirm' => ['required', 'boolean'],
            'weekly' => ['required', 'array:'.implode(',', $dayKeys)],
            'weekly.*.enabled' => ['required', 'boolean'],
            'weekly.*.start' => self::TIME,
            'weekly.*.end' => self::TIME,
            'breaks' => ['present', 'array', 'max:'.ConsultationSchedule::MAX_BREAKS],
            'breaks.*.start' => self::TIME,
            'breaks.*.end' => self::TIME,
            'blocked' => ['present', 'array', 'max:'.ConsultationSchedule::MAX_BLOCKED],
            'blocked.*.date' => ['required', 'date_format:Y-m-d'],
            'blocked.*.from' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'blocked.*.to' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'blocked.*.reason' => ['nullable', 'string', 'max:120'],
        ]);

        $validator->after(function (Validator $v) use ($request) {
            foreach ((array) $request->input('weekly', []) as $day => $hours) {
                if (($hours['enabled'] ?? false) && ($hours['start'] ?? '') >= ($hours['end'] ?? '')) {
                    $v->errors()->add("weekly.{$day}.end", __('Closing time must be after opening time.'));
                }
            }

            foreach ((array) $request->input('breaks', []) as $i => $b) {
                if (($b['start'] ?? '') >= ($b['end'] ?? '')) {
                    $v->errors()->add("breaks.{$i}.end", __('A break must end after it starts.'));
                }
            }

            foreach ((array) $request->input('blocked', []) as $i => $b) {
                $from = $b['from'] ?? null;
                $to = $b['to'] ?? null;

                if (($from === null) !== ($to === null) || ($from !== null && $from >= $to)) {
                    $v->errors()->add("blocked.{$i}.to", __('Give both times (the second after the first), or leave both empty to block the whole day.'));
                }
            }
        });

        $data = $validator->validate();
        $data['blocked'] = array_values(array_map(fn (array $b) => ['date' => $b['date'], 'from' => $b['from'] ?? null, 'to' => $b['to'] ?? null, 'reason' => (string) ($b['reason'] ?? '')], $data['blocked']));
        $schedule->save($data);

        activity('consultations')->causedBy($request->user())->log('Consultation availability updated');

        $this->toast(['type' => 'success', 'message' => __('Availability saved. Customers see the new times straight away.')]);

        return back();
    }
}
