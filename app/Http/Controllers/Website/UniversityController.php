<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\University;
use App\Models\User;
use App\Support\Cms\SectionHydrator;
use App\Support\Cms\WebsiteSeo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * University profile (REQ §15, §21.8; not in Figma, derived from the blog
 * card grid and About page typography). The searchable list is the
 * "universities" CMS page, which the pathway finder submits to (roadmap Q8).
 */
class UniversityController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $university = University::query()->where('slug', $slug)
            ->with(['logo', 'banner', 'country:id,name', 'seo.ogImage', 'courses' => fn ($q) => $q->where('is_active', true)->orderBy('level')->orderBy('name')])
            ->firstOrFail();

        abort_if(! $university->is_active && ! $user?->can('universities.manage'), 404);

        return Inertia::render('website/University', [
            'university' => [
                ...SectionHydrator::university($university),
                'type' => $university->type,
                'website' => $university->website,
                'address' => $university->address,
                'established' => $university->established_year,
                'ranking' => $university->ranking,
                'description' => (string) $university->description,
                'admission_requirements' => (string) $university->admission_requirements,
                'scholarships' => (string) $university->scholarships,
                'gallery' => array_map(fn (array $m) => ['url' => $m['url'], 'alt' => (string) $m['alt'], 'width' => $m['width'], 'height' => $m['height']], $university->galleryMedia()),
                'courses' => $university->courses->map(fn (Course $c) => [
                    ...SectionHydrator::course($c),
                    'intakes' => array_map(fn (int $m) => now()->startOfYear()->setMonth($m)->format('M'), $c->intakes ?? []),
                    'deadline' => $c->application_deadline?->format('d M Y'),
                    'scholarship' => $c->scholarship,
                    'requirements' => $c->requirements,
                ])->values(),
            ],
            'seo' => WebsiteSeo::make($university->name, $university->summary, $university->seo, $university->banner ?? $university->logo, noindex: ! $university->is_active, crumbs: [['name' => 'Universities', 'url' => WebsiteSeo::canonicalFor('universities')], ['name' => $university->name, 'url' => WebsiteSeo::canonicalFor("universities/{$university->slug}")]]),
        ]);
    }
}
