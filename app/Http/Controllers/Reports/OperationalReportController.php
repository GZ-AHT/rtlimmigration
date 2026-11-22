<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\ResolvesAreaRoutes;
use App\Http\Controllers\Controller;
use App\Support\Reports\OperationalReports;
use App\Support\Reports\ReportExporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * REQ §24 Reports & Analytics, beyond the finance reports. Route middleware
 * checks reports.view; each report then checks the permission of the data it
 * covers (OperationalReports::allowed) and exporting needs reports.export.
 */
class OperationalReportController extends Controller
{
    use ResolvesAreaRoutes;

    /** Rows shown on screen; exports carry every row. */
    private const SCREEN_ROWS = 100;

    public function hub(Request $request, OperationalReports $reports): Response
    {
        $user = $request->user();
        $financial = [
            ['key' => 'financial', 'title' => 'Income & expenses', 'description' => 'Income, refunds, expenses and profit by month.', 'group' => 'Finance', 'url' => $this->routeOrNull($request, 'reports.financial')],
            ['key' => 'counselor-collection', 'title' => 'Counselor-wise collection', 'description' => 'Invoiced, collected and outstanding per counselor.', 'group' => 'Finance', 'url' => $this->routeOrNull($request, 'reports.counselors')],
            ['key' => 'student-accounts', 'title' => 'Student-wise accounts', 'description' => 'Fees, payments, refunds and balance for each student.', 'group' => 'Finance', 'url' => $this->routeOrNull($request, 'reports.students')],
        ];

        return Inertia::render('reports/Index', [
            'reports' => collect($user->can('reports.financial') ? $financial : [])
                ->filter(fn (array $r) => $r['url'] !== null)
                ->concat(collect(OperationalReports::available($user))->map(fn (array $r, string $key) => [
                    'key' => $key, ...$r, 'url' => $this->areaRoute($request, 'reports.show', ['report' => $key]),
                ])->values())
                ->groupBy('group')->map(fn ($group, string $name) => ['name' => $name, 'items' => $group->values()])->values(),
            'exportsUrl' => $user->can('reports.export') ? $this->routeOrNull($request, 'reports.downloads') : null,
        ]);
    }

    public function show(Request $request, string $report, OperationalReports $reports): Response
    {
        abort_unless(OperationalReports::allowed($report, $request->user()), 404);

        [$from, $to, $filters] = $this->period($request);
        $data = $reports->build($report, $from, $to, $request->user());
        $rows = LazyCollection::make($data['rows'])->take(self::SCREEN_ROWS + 1)->values()->all();
        $canExport = $request->user()->can('reports.export');

        return Inertia::render('reports/Show', [
            'report' => ['key' => $report, 'title' => OperationalReports::REPORTS[$report][0], 'description' => OperationalReports::REPORTS[$report][1]],
            'filters' => $filters,
            'stats' => $data['stats'],
            'breakdowns' => $data['breakdowns'],
            'headings' => $data['headings'],
            'rows' => array_slice($rows, 0, self::SCREEN_ROWS),
            'truncated' => count($rows) > self::SCREEN_ROWS,
            'hubUrl' => $this->areaRoute($request, 'reports.hub'),
            'exports' => $canExport ? collect(ReportExporter::FORMATS)->mapWithKeys(fn (string $format) => [
                $format => $this->areaRoute($request, 'reports.file', ['report' => $report, 'format' => $format, ...$filters]),
            ]) : null,
        ]);
    }

    public function download(Request $request, string $report, string $format, OperationalReports $reports, ReportExporter $exporter): SymfonyResponse
    {
        abort_unless(OperationalReports::allowed($report, $request->user()) && in_array($format, ReportExporter::FORMATS, true), 404);
        abort_unless($request->user()->can('reports.export'), 403);

        [$from, $to] = $this->period($request);
        $data = $reports->build($report, $from, $to, $request->user());
        $title = OperationalReports::REPORTS[$report][0];

        activity('reports')->causedBy($request->user())->withProperties(['report' => $report, 'format' => $format, 'from' => $from->toDateString(), 'to' => $to->toDateString()])->log('Exported report: '.$title);

        return $exporter->download($format, $title, $from->format('d M Y').' to '.$to->format('d M Y'), $data['headings'], $data['rows']);
    }

    private function routeOrNull(Request $request, string $name): ?string
    {
        return $this->areaHas($request, $name) ? $this->areaRoute($request, $name) : null;
    }

    /**
     * The reporting period, defaulting to the last 30 days.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: array{from: string, to: string}}
     */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : CarbonImmutable::now()->subDays(29)->startOfDay();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();

        return [$from, $to, ['from' => $from->toDateString(), 'to' => $to->toDateString()]];
    }
}
