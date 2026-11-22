<?php

use App\Enums\Role;
use App\Models\Counselor;
use App\Models\Student;
use App\Models\User;
use App\Support\Reports\OperationalReports;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
    $this->leadManager = $this->userWithRole(Role::LeadManager);
    $this->accounts = $this->userWithRole(Role::Accounts);
    $this->counselor = $this->userWithRole(Role::Counselor);
    Counselor::create(['user_id' => $this->counselor->id]);
    $this->otherCounselor = $this->userWithRole(Role::Counselor);
    Counselor::create(['user_id' => $this->otherCounselor->id]);
});

/** @return list<string> report keys listed on the hub for a user. */
function hubKeys($test, User $user, string $area): array
{
    $keys = [];
    $test->actingAs($user)->get(route("{$area}.reports.hub"))->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$keys) {
            $keys = collect($page->toArray()['props']['reports'])->pluck('items')->flatten(1)->pluck('key')->all();
        });

    return $keys;
}

it('lists only the reports a role may open', function () {
    $all = array_keys(OperationalReports::REPORTS);

    expect(hubKeys($this, $this->admin, 'admin'))->toContain(...$all)->toContain('financial');
    expect(hubKeys($this, $this->leadManager, 'admin'))->toEqualCanonicalizing(['counselor-performance', 'tickets', 'live-chat'])
        ->and(hubKeys($this, $this->counselor, 'counselor'))->toEqualCanonicalizing(['registrations', 'university-applications', 'visa-applications'])
        ->and(hubKeys($this, $this->accounts, 'accounts'))->toContain('financial', 'registrations')->not->toContain('tickets', 'email-activity');
});

it('refuses reports outside the role and the hub to roles without reports.view', function () {
    $this->actingAs($this->leadManager)->get(route('admin.reports.show', 'registrations'))->assertNotFound();
    $this->actingAs($this->leadManager)->get(route('admin.reports.show', 'website-traffic'))->assertNotFound();
    $this->actingAs($this->counselor)->get(route('counselor.reports.show', 'tickets'))->assertNotFound();
    $this->actingAs($this->admin)->get(route('admin.reports.show', 'no-such-report'))->assertNotFound();

    $student = $this->userWithRole(Role::Student);
    $this->actingAs($student)->get('/admin/reports')->assertForbidden();
});

it('renders every report for a super admin', function () {
    Student::factory()->create(['counselor_id' => $this->counselor->id]);

    foreach (array_keys(OperationalReports::REPORTS) as $key) {
        $this->actingAs($this->admin)->get(route('admin.reports.show', $key))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('reports/Show')->where('report.key', $key)->has('stats')->has('headings'));
    }
});

it('counts only the students a counselor may see', function () {
    Student::factory()->count(2)->create(['counselor_id' => $this->counselor->id]);
    Student::factory()->count(3)->create(['counselor_id' => $this->otherCounselor->id]);

    $this->actingAs($this->counselor)->get(route('counselor.reports.show', 'registrations'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.0.value', 2)->has('rows', 2));

    $this->actingAs($this->admin)->get(route('admin.reports.show', 'registrations'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.0.value', 5));
});

it('filters a report by the chosen period', function () {
    Student::factory()->create(['counselor_id' => $this->counselor->id, 'created_at' => now()->subDays(90)]);
    Student::factory()->create(['counselor_id' => $this->counselor->id]);

    $this->actingAs($this->admin)->get(route('admin.reports.show', ['report' => 'registrations', 'from' => now()->subDays(100)->toDateString(), 'to' => now()->toDateString()]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.0.value', 2));
    $this->actingAs($this->admin)->get(route('admin.reports.show', 'registrations'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.0.value', 1));
    $this->actingAs($this->admin)->get(route('admin.reports.show', ['report' => 'registrations', 'from' => '2026-02-01', 'to' => '2026-01-01']))
        ->assertSessionHasErrors('to');
});

it('exports a report, records it, and keeps it to roles with reports.export', function () {
    $student = Student::factory()->create(['counselor_id' => $this->counselor->id]);

    $csv = $this->actingAs($this->admin)->get(route('admin.reports.file', ['report' => 'registrations', 'format' => 'csv']));
    $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csv->streamedContent())->toContain($student->student_number);
    expect(Activity::query()->where('description', 'like', 'Exported report: Student registrations%')->count())->toBe(1);

    $this->actingAs($this->admin)->get(route('admin.reports.file', ['report' => 'registrations', 'format' => 'xlsx']))->assertOk();
    $this->actingAs($this->admin)->get('/admin/reports/view/registrations/docx')->assertNotFound();
    $this->actingAs($this->counselor)->get(route('counselor.reports.file', ['report' => 'registrations', 'format' => 'csv']))->assertForbidden();
});

it('protects spreadsheet exports from formula injection', function () {
    $student = Student::factory()->create();
    $student->user->update(['name' => '=HYPERLINK("http://evil.test","x")']);

    $csv = $this->actingAs($this->admin)->get(route('admin.reports.file', ['report' => 'registrations', 'format' => 'csv']))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

describe('website traffic', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->seed([SettingsSeeder::class, WebsiteContentSeeder::class]);
    });

    it('counts public page views once per request without storing an IP address', function () {
        $agent = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1'];

        $this->withServerVariables($agent)->get('/')->assertOk();
        $this->withServerVariables($agent)->get('/')->assertOk();
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120'])->get('/')->assertOk();

        $row = DB::table('page_views')->where('path', '/')->get();
        expect($row)->toHaveCount(2)->and((int) $row->sum('hits'))->toBe(3)
            ->and(strlen($row->first()->visitor_hash))->toBe(40);

        $this->actingAs($this->admin)->get(route('admin.reports.show', 'website-traffic'))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.0.value', 3)->where('stats.1.value', 2)->where('stats.3.value', '/'));
    });

    it('skips bots, staff, prefetches and non-pages', function () {
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Googlebot/2.1'])->get('/')->assertOk();
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])->actingAs($this->admin)->get('/')->assertOk();
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])->get('/sitemap.xml')->assertOk();
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120'])->get('/definitely-missing-page')->assertNotFound();

        expect(DB::table('page_views')->count())->toBe(0);
    });
});

it('runs the same number of queries however many rows a report has', function () {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.reports.show', 'registrations'))->assertOk();

        return count(DB::getQueryLog());
    };

    $queries(); // warm the permission cache so only data queries are compared
    Student::factory()->count(2)->create(['counselor_id' => $this->counselor->id]);
    $few = $queries();
    Student::factory()->count(15)->create(['counselor_id' => $this->counselor->id]);

    expect($queries())->toBe($few);
});
