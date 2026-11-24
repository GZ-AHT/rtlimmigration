<?php

namespace App\Http\Controllers\Admin\Consultations;

use App\Http\Controllers\Controller;
use App\Models\ConsultationBooking;
use App\Support\Consultations\ConsultationSchedule;
use App\Support\Presenters\ConsultationPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Bookings on a month, week or day calendar, drawn in the schedule's own timezone. */
class CalendarController extends Controller
{
    public function index(Request $request, ConsultationSchedule $schedule): Response
    {
        Gate::authorize('consultations.view');

        $data = $request->validate([
            'view' => ['nullable', Rule::in(['month', 'week', 'day'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $zone = $schedule->timezone();
        $view = $data['view'] ?? 'month';
        $date = CarbonImmutable::parse($data['date'] ?? 'now', $zone)->startOfDay();

        // The first and last day shown (a month view starts on the Monday of its first week).
        [$from, $to] = match ($view) {
            'day' => [$date, $date],
            'week' => [$date->startOfWeek(), $date->startOfWeek()->addDays(6)],
            default => [$date->startOfMonth()->startOfWeek(), $date->endOfMonth()->startOfDay()->endOfWeek()->startOfDay()],
        };

        $bookings = ConsultationBooking::query()
            ->where('starts_at', '<', $to->addDay()->utc())->where('ends_at', '>', $from->utc())
            ->orderBy('starts_at')->get();

        return Inertia::render('admin/consultations/Calendar', [
            'view' => $view,
            'date' => $date->format('Y-m-d'),
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'timezone' => $zone,
            'bookings' => $bookings->map(fn (ConsultationBooking $b) => ConsultationPresenter::row($b))->values()->all(),
            'hours' => $schedule->weekly(),
        ]);
    }
}
