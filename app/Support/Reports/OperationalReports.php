<?php

namespace App\Support\Reports;

use App\Enums\ApplicationStatus;
use App\Enums\ChatStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\LeadStatus;
use App\Enums\StudentSource;
use App\Enums\TicketStatus;
use App\Enums\VisaKind;
use App\Models\Application;
use App\Models\ChatConversation;
use App\Models\Counselor;
use App\Models\EmailCampaign;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\PartnerApplication;
use App\Models\Student;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The non-financial reports of REQ §24: registrations, counselor
 * performance, applications, partners, email, tickets, live chat and
 * website traffic. Each report returns the same shape (headline figures,
 * breakdowns and a table) so one screen and one exporter serve all of them.
 *
 * Who may open a report follows the permission of the data it describes, and
 * reports over students and applications only count records the viewer may
 * see (a counselor's report covers their own students).
 *
 * @phpstan-type Breakdown array{title: string, items: list<array{label: string, value: int, share: int}>}
 * @phpstan-type Report array{stats: list<array{label: string, value: string|int|float}>, breakdowns: list<Breakdown>, headings: list<string>, rows: iterable<int, list<string|int|float|null>>}
 */
class OperationalReports
{
    /** key => [title, description, group, permission]. */
    public const REPORTS = [
        'registrations' => ['Student registrations', 'New students, where they came from and who looks after them.', 'People', 'students.view'],
        'counselor-performance' => ['Counselor performance', 'Students, leads, conversions and visa decisions for each counselor.', 'People', 'counselors.view'],
        'university-applications' => ['University applications', 'Study applications: how many, where they are and how they ended.', 'Applications', 'applications.view'],
        'visa-applications' => ['Visa applications', 'Every application across all visa types, with outcomes by country and type.', 'Applications', 'applications.view'],
        'partner-applications' => ['Partner applications', 'Partnership requests received from the website and how they were decided.', 'Applications', 'partners.view'],
        'email-activity' => ['Email activity', 'Emails sent, failed, opened and clicked, with campaign counts.', 'Communication', 'email.manage'],
        'tickets' => ['Tickets & inquiries', 'Tickets by status, priority and category, and how fast they are resolved.', 'Communication', 'tickets.view'],
        'live-chat' => ['Live chat', 'Conversations by type and status, and how busy chat has been.', 'Communication', 'chat.view-all'],
        'website-traffic' => ['Website traffic', 'Page views, visits and the most viewed pages. Counted without cookies or IP addresses.', 'Website', 'seo.manage'],
    ];

    /** @return array<string, array{title: string, description: string, group: string}> */
    public static function available(User $user): array
    {
        $reports = [];

        foreach (self::REPORTS as $key => [$title, $description, $group, $permission]) {
            if ($user->can($permission)) {
                $reports[$key] = ['title' => $title, 'description' => $description, 'group' => $group];
            }
        }

        return $reports;
    }

    public static function allowed(string $key, User $user): bool
    {
        return isset(self::REPORTS[$key]) && $user->can(self::REPORTS[$key][3]);
    }

    /** @return Report */
    public function build(string $key, CarbonImmutable $from, CarbonImmutable $to, User $user): array
    {
        return match ($key) {
            'registrations' => $this->registrations($from, $to, $user),
            'counselor-performance' => $this->counselorPerformance($from, $to),
            'university-applications' => $this->applications($from, $to, $user, VisaKind::Study),
            'visa-applications' => $this->applications($from, $to, $user, null),
            'partner-applications' => $this->partners($from, $to),
            'email-activity' => $this->email($from, $to),
            'tickets' => $this->tickets($from, $to),
            'live-chat' => $this->chat($from, $to),
            'website-traffic' => $this->traffic($from, $to),
            default => abort(404),
        };
    }

    /** @return Report */
    private function registrations(CarbonImmutable $from, CarbonImmutable $to, User $user): array
    {
        $base = fn (): Builder => Student::query()->visibleTo($user)->whereBetween('students.created_at', [$from, $to]);
        $total = $base()->count();
        $assigned = $base()->whereNotNull('counselor_id')->count();
        $bySource = $this->counts($base(), 'source', fn (?string $v) => StudentSource::tryFrom((string) $v)?->label() ?? 'Unknown');

        return [
            'stats' => [
                ['label' => 'New students', 'value' => $total],
                ['label' => 'With a counselor', 'value' => $assigned],
                ['label' => 'Not yet assigned', 'value' => $total - $assigned],
                ['label' => 'Main source', 'value' => $bySource->keys()->first() ?? '—'],
            ],
            'breakdowns' => [$this->breakdown('By source', $bySource)],
            'headings' => ['Registered', 'Student no.', 'Name', 'Email', 'Phone', 'Source', 'Counselor'],
            'rows' => $base()->with(['user', 'counselor'])->latest('students.id')->lazy(500)->map(fn (Student $s) => [
                $s->created_at?->format('Y-m-d'), $s->student_number, $s->user->name, $s->user->contact_email, $s->phone,
                $s->source->label(), $s->counselor?->name,
            ]),
        ];
    }

    /** @return Report */
    private function counselorPerformance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counselors = Counselor::query()->with('user')->get()->sortBy(fn (Counselor $c) => $c->user->name);

        $students = Student::query()->selectRaw('counselor_id, count(*) as total')->whereNotNull('counselor_id')->groupBy('counselor_id')->pluck('total', 'counselor_id');
        $newStudents = Student::query()->selectRaw('counselor_id, count(*) as total')->whereNotNull('counselor_id')->whereBetween('created_at', [$from, $to])->groupBy('counselor_id')->pluck('total', 'counselor_id');
        $leads = Lead::query()->selectRaw('counselor_id, count(*) as total')->whereNotNull('counselor_id')->whereBetween('assigned_at', [$from, $to])->groupBy('counselor_id')->pluck('total', 'counselor_id');
        $converted = Lead::query()->selectRaw('counselor_id, count(*) as total')->whereNotNull('counselor_id')->where('status', LeadStatus::Converted)->whereBetween('converted_at', [$from, $to])->groupBy('counselor_id')->pluck('total', 'counselor_id');
        $decisions = Application::query()
            ->join('students', 'students.id', '=', 'applications.student_id')
            ->whereBetween('applications.decided_at', [$from, $to])
            ->whereNotNull('students.counselor_id')
            ->groupBy('students.counselor_id')
            ->selectRaw("students.counselor_id as cid, sum(applications.status = 'approved') as approved, sum(applications.status = 'rejected') as rejected")
            ->get()->keyBy('cid');

        $rows = $counselors->map(function (Counselor $c) use ($students, $newStudents, $leads, $converted, $decisions) {
            $id = $c->user_id;
            $assigned = (int) ($leads[$id] ?? 0);
            $won = (int) ($converted[$id] ?? 0);
            $d = $decisions[$id] ?? null;

            return [
                $c->user->name, (int) ($students[$id] ?? 0), (int) ($newStudents[$id] ?? 0), $assigned, $won,
                $assigned > 0 ? round($won / $assigned * 100, 1).'%' : '—', (int) ($d->approved ?? 0), (int) ($d->rejected ?? 0),
            ];
        })->values();

        return [
            'stats' => [
                ['label' => 'Counselors', 'value' => $rows->count()],
                ['label' => 'Leads assigned', 'value' => (int) $rows->sum(fn (array $r) => $r[3])],
                ['label' => 'Leads converted', 'value' => (int) $rows->sum(fn (array $r) => $r[4])],
                ['label' => 'Visas approved', 'value' => (int) $rows->sum(fn (array $r) => $r[6])],
            ],
            'breakdowns' => [$this->breakdown('Students handled', $rows->mapWithKeys(fn (array $r) => [$r[0] => $r[1]])->filter()->sortDesc()->take(8))],
            'headings' => ['Counselor', 'Students (all)', 'New students', 'Leads assigned', 'Leads converted', 'Conversion', 'Visas approved', 'Visas refused'],
            'rows' => $rows,
        ];
    }

    /** @return Report */
    private function applications(CarbonImmutable $from, CarbonImmutable $to, User $user, ?VisaKind $kind): array
    {
        $base = function () use ($from, $to, $user, $kind): Builder {
            $query = Application::query()->visibleTo($user)->whereBetween('applications.created_at', [$from, $to]);

            return $kind ? $query->whereHas('visaType', fn (Builder $q) => $q->where('kind', $kind)) : $query;
        };

        $byStatus = $this->counts($base(), 'status', fn (?string $v) => ApplicationStatus::tryFrom((string) $v)?->label() ?? 'Unknown');
        $get = fn (ApplicationStatus $s) => (int) ($byStatus[$s->label()] ?? 0);
        $approved = $get(ApplicationStatus::Approved);
        $refused = $get(ApplicationStatus::Rejected);
        $byCountry = $base()->join('countries', 'countries.id', '=', 'applications.country_id')->selectRaw('countries.name as label, count(*) as total')->groupBy('countries.name')->orderByDesc('total')->limit(8)->pluck('total', 'label');
        $byType = $base()->join('visa_types', 'visa_types.id', '=', 'applications.visa_type_id')->selectRaw('visa_types.name as label, count(*) as total')->groupBy('visa_types.name')->orderByDesc('total')->limit(8)->pluck('total', 'label');

        return [
            'stats' => [
                ['label' => 'Applications', 'value' => (int) $byStatus->sum()],
                ['label' => 'In progress', 'value' => $get(ApplicationStatus::InProgress)],
                ['label' => 'Approved', 'value' => $approved],
                ['label' => 'Approval rate', 'value' => $approved + $refused > 0 ? round($approved / ($approved + $refused) * 100).'%' : '—'],
            ],
            'breakdowns' => $kind === null
                ? [$this->breakdown('By status', $byStatus), $this->breakdown('By country', $byCountry), $this->breakdown('By visa type', $byType)]
                : [$this->breakdown('By status', $byStatus), $this->breakdown('By country', $byCountry)],
            'headings' => ['Application', 'Created', 'Student', 'Student no.', 'Visa type', 'Country', 'Organisation', 'Stage', 'Status', 'Decision', 'Decided'],
            'rows' => $base()->with(['student.user', 'visaType', 'country', 'stage'])->latest('applications.id')->lazy(500)->map(fn (Application $a) => [
                $a->application_number, $a->created_at?->format('Y-m-d'), $a->student->user->name, $a->student->student_number, $a->visaType->name,
                $a->country?->name, $a->organisation, $a->stage?->name, $a->status->label(), $a->decision, $a->decided_at?->format('Y-m-d'),
            ]),
        ];
    }

    /** @return Report */
    private function partners(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = fn (): Builder => PartnerApplication::query()->whereBetween('partner_applications.created_at', [$from, $to]);
        $label = fn (?string $v) => PartnerApplication::STATUSES[$v][0] ?? ucfirst((string) $v);
        $byStatus = $this->counts($base(), 'status', $label);
        $byType = $this->counts($base(), 'type', fn (?string $v) => $v !== null && $v !== '' ? ucfirst(str_replace('_', ' ', $v)) : 'Not stated');
        $byCountry = $base()->leftJoin('countries', 'countries.id', '=', 'partner_applications.country_id')->selectRaw("coalesce(countries.name, 'Not stated') as label, count(*) as total")->groupBy('label')->orderByDesc('total')->limit(8)->pluck('total', 'label');

        return [
            'stats' => [
                ['label' => 'Applications', 'value' => (int) $byStatus->sum()],
                ['label' => 'Pending review', 'value' => (int) ($byStatus[$label('pending')] ?? 0)],
                ['label' => 'Approved', 'value' => (int) ($byStatus[$label('approved')] ?? 0)],
                ['label' => 'Rejected', 'value' => (int) ($byStatus[$label('rejected')] ?? 0)],
            ],
            'breakdowns' => [$this->breakdown('By status', $byStatus), $this->breakdown('By type', $byType), $this->breakdown('By country', $byCountry)],
            'headings' => ['Received', 'Organisation', 'Contact', 'Email', 'Phone', 'Country', 'City', 'Type', 'Students per year', 'Status', 'Reviewed by', 'Reviewed on'],
            'rows' => $base()->with(['country', 'reviewer'])->latest('partner_applications.id')->lazy(500)->map(fn (PartnerApplication $p) => [
                $p->created_at?->format('Y-m-d'), $p->organisation, $p->contact_name, $p->email, $p->phone, $p->country?->name, $p->city, $p->type,
                $p->students_per_year, $label($p->status), $p->reviewer?->name, $p->reviewed_at?->format('Y-m-d'),
            ]),
        ];
    }

    /** @return Report */
    private function email(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = fn (): Builder => EmailMessage::query()->whereBetween('email_messages.created_at', [$from, $to]);
        $byStatus = $this->counts($base(), 'status', fn (?string $v) => EmailMessageStatus::tryFrom((string) $v)?->label() ?? 'Unknown');
        $sent = (int) ($byStatus[EmailMessageStatus::Sent->label()] ?? 0);
        $opened = $base()->whereNotNull('opened_at')->count();
        $clicked = $base()->whereNotNull('clicked_at')->count();
        $byDay = $base()->selectRaw('date(email_messages.created_at) as label, count(*) as total')->groupBy('label')->orderBy('label')->pluck('total', 'label');

        return [
            'stats' => [
                ['label' => 'Emails', 'value' => (int) $byStatus->sum()],
                ['label' => 'Sent', 'value' => $sent],
                ['label' => 'Failed', 'value' => (int) ($byStatus[EmailMessageStatus::Failed->label()] ?? 0)],
                ['label' => 'Opened (lower bound)', 'value' => $sent > 0 ? $opened.' ('.round($opened / $sent * 100).'%)' : $opened],
                ['label' => 'Clicked', 'value' => $clicked],
                ['label' => 'Campaigns', 'value' => EmailCampaign::query()->whereBetween('created_at', [$from, $to])->count()],
            ],
            'breakdowns' => [$this->breakdown('By status', $byStatus), $this->breakdown('Latest days', $byDay->take(-10))],
            'headings' => ['Date', 'To', 'Subject', 'Status', 'Opens', 'Clicks', 'Sent by', 'Error'],
            'rows' => $base()->with('sender')->latest('email_messages.id')->lazy(500)->map(fn (EmailMessage $m) => [
                $m->created_at?->format('Y-m-d H:i'), $m->to_email, $m->subject, $m->status->label(), (int) $m->open_count, (int) $m->click_count, $m->sender?->name, $m->error,
            ]),
        ];
    }

    /** @return Report */
    private function tickets(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = fn (): Builder => Ticket::query()->whereBetween('tickets.created_at', [$from, $to]);
        $byStatus = $this->counts($base(), 'status', fn (?string $v) => TicketStatus::tryFrom((string) $v)?->label() ?? 'Unknown');
        $byPriority = $this->counts($base(), 'priority', fn (?string $v) => ucfirst((string) $v));
        $byCategory = $base()->leftJoin('ticket_categories', 'ticket_categories.id', '=', 'tickets.category_id')->selectRaw("coalesce(ticket_categories.name, 'Uncategorised') as label, count(*) as total")->groupBy('label')->orderByDesc('total')->limit(8)->pluck('total', 'label');
        $hours = $base()->whereNotNull('resolved_at')->selectRaw('avg(timestampdiff(minute, tickets.created_at, tickets.resolved_at)) / 60 as hours')->value('hours');
        $total = (int) $byStatus->sum();
        $open = (int) ($byStatus[TicketStatus::Open->label()] ?? 0) + (int) ($byStatus[TicketStatus::Pending->label()] ?? 0);

        return [
            'stats' => [
                ['label' => 'Tickets', 'value' => $total],
                ['label' => 'Still open', 'value' => $open],
                ['label' => 'Resolved or closed', 'value' => $total - $open],
                ['label' => 'Average time to resolve', 'value' => $hours === null ? '—' : round((float) $hours, 1).' h'],
            ],
            'breakdowns' => [$this->breakdown('By status', $byStatus), $this->breakdown('By priority', $byPriority), $this->breakdown('By category', $byCategory)],
            'headings' => ['Ticket', 'Created', 'Subject', 'From', 'Category', 'Priority', 'Status', 'Assigned to', 'Resolved'],
            'rows' => $base()->with(['category', 'assignee'])->latest('tickets.id')->lazy(500)->map(fn (Ticket $t) => [
                $t->ticket_number, $t->created_at?->format('Y-m-d'), $t->subject, $t->requester_name, $t->category?->name,
                ucfirst($t->priority->value), $t->status->label(), $t->assignee?->name, $t->resolved_at?->format('Y-m-d'),
            ]),
        ];
    }

    /** @return Report */
    private function chat(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = fn (): Builder => ChatConversation::query()->whereBetween('chat_conversations.created_at', [$from, $to]);
        $byKind = $this->counts($base(), 'kind', fn (?string $v) => ucfirst(str_replace('_', ' ', (string) $v)));
        $byStatus = $this->counts($base(), 'status', fn (?string $v) => ChatStatus::tryFrom((string) $v)?->label() ?? 'Unknown');
        $total = (int) $byStatus->sum();
        $messages = (int) $base()->sum('message_count');

        return [
            'stats' => [
                ['label' => 'Conversations', 'value' => $total],
                ['label' => 'Open now', 'value' => (int) ($byStatus[ChatStatus::Open->label()] ?? 0)],
                ['label' => 'Messages', 'value' => $messages],
                ['label' => 'Messages per chat', 'value' => $total > 0 ? round($messages / $total, 1) : '—'],
            ],
            'breakdowns' => [$this->breakdown('By type', $byKind), $this->breakdown('By status', $byStatus)],
            'headings' => ['Reference', 'Started', 'Type', 'Visitor', 'Email', 'Status', 'Messages', 'Assigned to', 'Last message'],
            'rows' => $base()->with('assignee')->latest('chat_conversations.id')->lazy(500)->map(fn (ChatConversation $c) => [
                $c->reference, $c->created_at?->format('Y-m-d H:i'), ucfirst(str_replace('_', ' ', $c->kind->value)),
                $c->visitor_name, $c->visitor_email, $c->status->label(), (int) $c->message_count, $c->assignee?->name, $c->last_message_at?->format('Y-m-d H:i'),
            ]),
        ];
    }

    /** @return Report */
    private function traffic(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = [$from->toDateString(), $to->toDateString()];
        $views = fn () => DB::table('page_views')->whereBetween('viewed_on', $range);
        $daily = $views()->selectRaw('viewed_on, count(distinct visitor_hash) as visits, sum(hits) as views')->groupBy('viewed_on')->orderBy('viewed_on')->get();
        $totalViews = (int) $daily->sum('views');
        $visits = (int) $daily->sum('visits');
        $top = $views()->selectRaw('path as label, sum(hits) as total')->groupBy('path')->orderByDesc('total')->limit(10)->pluck('total', 'label');

        return [
            'stats' => [
                ['label' => 'Page views', 'value' => $totalViews],
                ['label' => 'Visits', 'value' => $visits],
                ['label' => 'Pages per visit', 'value' => $visits > 0 ? round($totalViews / $visits, 1) : '—'],
                ['label' => 'Most viewed page', 'value' => $top->keys()->first() ?? '—'],
            ],
            'breakdowns' => [$this->breakdown('Most viewed pages', $top)],
            'headings' => ['Date', 'Visits', 'Page views'],
            'rows' => $daily->map(fn (object $d) => [$d->viewed_on, (int) $d->visits, (int) $d->views])->values(),
        ];
    }

    /**
     * Rows of a query counted by one column, keyed by a readable label,
     * largest first. Different raw values that share a label are added up.
     *
     * @param  Builder<*>  $query
     * @param  callable(?string): string  $label
     * @return Collection<string, int>
     */
    private function counts(Builder $query, string $column, callable $label): Collection
    {
        $table = $query->getModel()->getTable();
        $counts = [];

        foreach ($query->select(["{$table}.{$column} as bucket"])->selectRaw('count(*) as total')->groupBy('bucket')->get() as $row) {
            $raw = $row->getAttributes()['bucket'];
            $name = $label($raw === null ? null : (string) $raw);
            $counts[$name] = ($counts[$name] ?? 0) + (int) $row->getAttributes()['total'];
        }

        arsort($counts);

        return collect($counts);
    }

    /**
     * @param  iterable<array-key, int|string>  $counts
     * @return Breakdown
     */
    private function breakdown(string $title, iterable $counts): array
    {
        $whole = 0;

        foreach ($counts as $value) {
            $whole += (int) $value;
        }

        $items = [];

        foreach ($counts as $label => $value) {
            $items[] = ['label' => (string) $label, 'value' => (int) $value, 'share' => (int) round((int) $value / max(1, $whole) * 100)];
        }

        return ['title' => $title, 'items' => $items];
    }
}
