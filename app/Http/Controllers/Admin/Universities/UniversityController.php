<?php

namespace App\Http\Controllers\Admin\Universities;

use App\Enums\StudyLevel;
use App\Enums\VisaKind;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Course;
use App\Models\Media;
use App\Models\University;
use App\Support\Content\RichText;
use App\Support\Content\SeoRules;
use App\Support\Content\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * REQ §15: All / Add / Edit universities, profile, logo, banner, gallery,
 * location, courses, tuition, requirements, deadlines, scholarships,
 * intakes, SEO, featured and status.
 */
class UniversityController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('universities.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'featured'])],
        ]);

        $universities = University::query()
            ->with(['country:id,name', 'logo'])
            ->withCount('courses')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($s, '%_\\').'%')->orWhere('city', 'like', '%'.addcslashes($s, '%_\\').'%')))
            ->when($filters['country'] ?? null, fn (Builder $q, int|string $c) => $q->where('country_id', $c))
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => match ($s) {
                'active' => $q->where('is_active', true),
                'inactive' => $q->where('is_active', false),
                default => $q->where('is_featured', true),
            })
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (University $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'country' => $u->country->name,
                'city' => $u->city,
                'logo' => $u->logo?->url(),
                'courses_count' => $u->courses_count,
                'is_featured' => $u->is_featured,
                'is_active' => $u->is_active,
            ]);

        return Inertia::render('admin/universities/Index', [
            'universities' => $universities,
            'filters' => ['search' => $filters['search'] ?? '', 'country' => isset($filters['country']) ? (string) $filters['country'] : '', 'status' => $filters['status'] ?? ''],
            'countries' => $this->countryOptions(),
            'canManage' => $request->user()->can('universities.manage'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('universities.manage');

        return $this->form(null);
    }

    public function edit(University $university): Response
    {
        Gate::authorize('universities.view');

        return $this->form($university->load(['logo', 'banner', 'courses', 'seo.ogImage']));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('universities.manage');

        $university = DB::transaction(function () use ($request) {
            $university = new University;
            $this->fill($request, $university);

            return $university;
        });

        $this->toast(['type' => 'success', 'message' => __('University saved. Add its courses below.')]);

        return redirect()->route('admin.universities.edit', $university);
    }

    public function update(Request $request, University $university): RedirectResponse
    {
        Gate::authorize('universities.manage');

        DB::transaction(fn () => $this->fill($request, $university));
        $this->toast(['type' => 'success', 'message' => __('University saved.')]);

        return back();
    }

    public function destroy(University $university): RedirectResponse
    {
        Gate::authorize('universities.manage');

        $university->delete();
        $this->toast(['type' => 'success', 'message' => __('University and its courses deleted.')]);

        return redirect()->route('admin.universities.index');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        Gate::authorize('universities.manage');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $count = University::query()->whereKey($data['ids'])->get()->each->delete()->count();

        $this->toast(['type' => 'success', 'message' => trans_choice(':count university and its courses deleted.|:count universities and their courses deleted.', $count, ['count' => $count])]);

        return back();
    }

    public function storeCourse(Request $request, University $university): RedirectResponse
    {
        Gate::authorize('universities.manage');

        $data = $this->courseData($request);
        $university->courses()->create([...$data, 'slug' => $this->courseSlug($university, $data['name'])]);
        $this->toast(['type' => 'success', 'message' => __('Course added.')]);

        return back();
    }

    public function updateCourse(Request $request, University $university, Course $course): RedirectResponse
    {
        Gate::authorize('universities.manage');
        abort_unless($course->university_id === $university->id, 404);

        $data = $this->courseData($request);
        $course->update([...$data, 'slug' => $this->courseSlug($university, $data['name'], $course->id)]);
        $this->toast(['type' => 'success', 'message' => __('Course saved.')]);

        return back();
    }

    public function destroyCourse(University $university, Course $course): RedirectResponse
    {
        Gate::authorize('universities.manage');
        abort_unless($course->university_id === $university->id, 404);

        $course->delete();
        $this->toast(['type' => 'success', 'message' => __('Course deleted.')]);

        return back();
    }

    private function form(?University $u): Response
    {
        return Inertia::render('admin/universities/Form', [
            'university' => $u ? [
                'id' => $u->id,
                'name' => $u->name,
                'slug' => $u->slug,
                'country_id' => (string) $u->country_id,
                'city' => $u->city ?? '',
                'address' => $u->address ?? '',
                'website' => $u->website ?? '',
                'type' => $u->type ?? '',
                'established_year' => $u->established_year ? (string) $u->established_year : '',
                'ranking' => $u->ranking ? (string) $u->ranking : '',
                'summary' => $u->summary ?? '',
                'description' => $u->description ?? '',
                'admission_requirements' => $u->admission_requirements ?? '',
                'scholarships' => $u->scholarships ?? '',
                'logo' => $u->logo?->present(),
                'banner' => $u->banner?->present(),
                'gallery' => $u->galleryMedia(),
                'is_featured' => $u->is_featured,
                'is_active' => $u->is_active,
                'seo' => $u->seoForForm(),
                'courses' => $u->courses->sortBy('name')->values()->map(fn (Course $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'level' => $c->level->value,
                    'level_label' => $c->level->label(),
                    'subject' => $c->subject ?? '',
                    'visa_kind' => $c->visa_kind->value,
                    'duration' => $c->duration ?? '',
                    'tuition_fee' => $c->tuition_fee ?? '',
                    'tuition_currency' => $c->tuition_currency ?? '',
                    'tuition_period' => $c->tuition_period ?? '',
                    'intakes' => $c->intakes ?? [],
                    'intake_label' => $c->intakeLabel(),
                    'application_deadline' => $c->application_deadline?->toDateString() ?? '',
                    'requirements' => $c->requirements ?? '',
                    'scholarship' => $c->scholarship ?? '',
                    'is_featured' => $c->is_featured,
                    'is_active' => $c->is_active,
                ]),
            ] : null,
            'options' => [
                'countries' => $this->countryOptions(),
                'levels' => StudyLevel::options(),
                'visaKinds' => array_values(array_filter(VisaKind::options(), fn ($o) => in_array($o['value'], ['study', 'work', 'exam'], true))),
            ],
            'canManage' => request()->user()->can('universities.manage'),
        ]);
    }

    private function fill(Request $request, University $university): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
            'type' => ['nullable', Rule::in(['public', 'private'])],
            'established_year' => ['nullable', 'integer', 'between:1000,'.(int) date('Y')],
            'ranking' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'summary' => ['nullable', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:100000'],
            'admission_requirements' => ['nullable', 'string', 'max:5000'],
            'scholarships' => ['nullable', 'string', 'max:5000'],
            'logo_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'banner_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
            'gallery' => ['array', 'max:20'],
            'gallery.*' => ['integer', Rule::exists(Media::class, 'id')],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            ...SeoRules::rules(),
        ]);

        $university->fill([
            ...Arr::except($data, ['seo', 'slug', 'description', 'gallery']),
            'slug' => Slug::unique(University::class, filled($data['slug'] ?? null) ? $data['slug'] : $data['name'], $university->id),
            'description' => RichText::clean($data['description'] ?? null) ?: null,
            'gallery' => array_values(array_map('intval', $data['gallery'] ?? [])),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        $university->saveSeo($data['seo'] ?? null);
    }

    /** @return array<string, mixed> */
    private function courseData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'level' => ['required', Rule::enum(StudyLevel::class)],
            'subject' => ['nullable', 'string', 'max:120'],
            'visa_kind' => ['required', Rule::in(['study', 'work', 'exam'])],
            'duration' => ['nullable', 'string', 'max:60'],
            'tuition_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'tuition_currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'tuition_period' => ['nullable', Rule::in(['year', 'semester', 'total'])],
            'intakes' => ['array', 'max:12'],
            'intakes.*' => ['integer', 'between:1,12', 'distinct'],
            'application_deadline' => ['nullable', 'date'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'scholarship' => ['nullable', 'string', 'max:5000'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $intakes = array_map('intval', $data['intakes'] ?? []);
        sort($intakes);

        return [
            ...$data,
            'tuition_currency' => isset($data['tuition_currency']) ? strtoupper($data['tuition_currency']) : null,
            'intakes' => $intakes,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function courseSlug(University $university, string $name, ?int $ignore = null): string
    {
        $base = str($name)->slug()->limit(200, '')->toString() ?: 'course';
        $slug = $base;
        $i = 2;

        while ($university->courses()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** @return list<array{value: string, label: string}> */
    private function countryOptions(): array
    {
        return array_values(Country::query()->where('is_destination', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all());
    }
}
