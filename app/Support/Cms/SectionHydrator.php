<?php

namespace App\Support\Cms;

use App\Enums\ContactChannel;
use App\Enums\StudyLevel;
use App\Http\Controllers\Website\SkillMigrationController;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Country;
use App\Models\Course;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Media;
use App\Models\Page;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\University;
use App\Support\Chat\ChatSecuritySettings;
use App\Support\Settings\SettingsRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns a page's stored sections into what the public website renders
 * (REQ §21.7, §21.8): hidden sections are dropped, image ids become
 * {url, alt, width, height}, and dynamic sections get their live records
 * (posts, FAQs, universities…). Only public fields leave the server.
 */
class SectionHydrator
{
    /** The homepage banner designs; a page shows one of them. */
    public const BANNERS = ['hero', 'hero_cover'];

    /** @var Collection<int, Media> */
    private Collection $media;

    public function __construct(private Request $request) {}

    /**
     * @return list<array{id: string, type: string, data: array<string, mixed>, settings: array<string, mixed>, records: array<string, mixed>|null}>
     */
    public function hydrate(Page $page): array
    {
        $sections = array_values(array_filter($this->banner($page->sections), fn (array $s) => $s['enabled'] && SectionTypes::get($s['type']) !== null));

        $ids = [];
        foreach ($sections as $section) {
            $this->collectImages(SectionTypes::get($section['type'])['fields'], $section['data'], $ids);
        }
        $this->media = Media::query()->whereKey(array_unique($ids))->get()->keyBy('id');

        // Only one section per page may read the query string (filters, paging).
        $listed = false;

        return array_map(function (array $section) use (&$listed) {
            $type = $section['type'];
            $data = $this->images(SectionTypes::get($type)['fields'], array_replace(SectionTypes::defaults($type), $section['data']));
            $full = (bool) ($data['all'] ?? false) && ! $listed;
            $listed = $listed || $full;

            return [
                'id' => $section['id'],
                'type' => $type,
                'data' => $data,
                'settings' => $section['settings'],
                'records' => $this->records($type, $data, $full),
            ];
        }, $sections);
    }

    /**
     * A page shows one banner: the first visible hero design. People who can
     * edit pages may preview the other design with ?banner=hero_cover (or
     * ?banner=hero) before switching to it.
     *
     * @param  list<array{id: string, type: string, enabled: bool, data: array<string, mixed>, settings: array<string, mixed>}>  $sections
     * @return list<array{id: string, type: string, enabled: bool, data: array<string, mixed>, settings: array<string, mixed>}>
     */
    private function banner(array $sections): array
    {
        $preview = (string) $this->request->query('banner', '');
        $previewing = in_array($preview, self::BANNERS, true)
            && $this->request->user()?->can('pages.manage')
            && in_array($preview, array_column($sections, 'type'), true);
        $shown = false;
        $out = [];

        foreach ($sections as $section) {
            if (in_array($section['type'], self::BANNERS, true)) {
                $visible = $previewing ? $section['type'] === $preview : $section['enabled'];

                if (! $visible || $shown) {
                    continue;
                }

                $shown = true;
                $section['enabled'] = true;
            }

            $out[] = $section;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @param  list<int>  $ids
     */
    private function collectImages(array $fields, array $data, array &$ids): void
    {
        foreach ($fields as $key => $field) {
            if (in_array($field['type'], ['image', 'video'], true) && is_numeric($data[$key] ?? null)) {
                $ids[] = (int) $data[$key];
            } elseif ($field['type'] === 'repeater') {
                foreach ((array) ($data[$key] ?? []) as $item) {
                    $this->collectImages($field['fields'], (array) $item, $ids);
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function images(array $fields, array $data): array
    {
        foreach ($fields as $key => $field) {
            if ($field['type'] === 'image') {
                $data[$key] = is_numeric($data[$key] ?? null) ? self::image($this->media->get((int) $data[$key])) : null;
            } elseif ($field['type'] === 'video') {
                $video = is_numeric($data[$key] ?? null) ? $this->media->get((int) $data[$key]) : null;
                $data[$key] = $video?->isVideo() ? ['url' => $video->url(), 'mime' => $video->mime_type] : null;
            } elseif ($field['type'] === 'repeater') {
                $data[$key] = array_values(array_map(fn ($item) => $this->images($field['fields'], (array) $item), (array) ($data[$key] ?? [])));
            }
        }

        return $data;
    }

    /** @return array{url: string, alt: string, width: int|null, height: int|null}|null */
    public static function image(?Media $media): ?array
    {
        return $media ? ['url' => $media->url(), 'alt' => (string) $media->alt, 'width' => $media->width, 'height' => $media->height] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function records(string $type, array $data, bool $full): ?array
    {
        $limit = (int) ($data['limit'] ?? 6);

        return match ($type) {
            'hero', 'hero_cover' => $data['show_finder'] ? $this->finder() : null,
            'testimonials' => ['items' => Testimonial::query()->with(['photo', 'country:id,name'])->where('is_active', true)
                ->when(! $full, fn (Builder $q) => $q->where('is_featured', true)->limit($limit))
                ->orderBy('sort_order')->orderByDesc('id')->get()->map(fn (Testimonial $t) => self::testimonial($t))->values()],
            'blog' => $full ? $this->blogListing() : ['items' => BlogPost::query()->live()->with(['featuredImage', 'category:id,name,slug', 'tags:id,name'])
                ->when($data['category'] !== '', fn (Builder $q) => $q->whereHas('category', fn (Builder $c) => $c->where('slug', $data['category'])))
                ->orderByDesc('is_featured')->orderByDesc('published_at')->limit($limit)->get()->map(fn (BlogPost $p) => self::post($p))->values()],
            'faq' => $this->faqs((bool) $data['all']),
            'universities' => $full ? $this->universityListing() : ['items' => University::query()->active()->where('is_featured', true)
                ->with(['logo', 'banner', 'country:id,name'])->withCount(['courses' => fn (Builder $q) => $q->where('is_active', true)])
                ->orderBy('sort_order')->orderBy('name')->limit($limit)->get()->map(fn (University $u) => self::university($u))->values()],
            'countries' => ['items' => Country::query()->destinations()
                ->withCount(['universities' => fn (Builder $q) => $q->where('is_active', true)])
                ->limit($limit)->get()->map(fn (Country $c) => ['id' => $c->id, 'name' => $c->name, 'iso2' => strtolower($c->iso2), 'universities' => $c->universities_count])->values()],
            'courses' => ['items' => Course::query()->active()->where('is_featured', true)
                ->whereHas('university', fn (Builder $q) => $q->where('is_active', true))
                ->with('university:id,name,slug,country_id', 'university.country:id,name')->limit($limit)->get()
                ->map(fn (Course $c) => self::course($c))->values()],
            'contact' => [
                'services' => Service::active()->get(['id', 'name'])->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name])->values(),
                'countries' => self::destinationOptions(),
                'channels' => array_values(array_filter(ContactChannel::options(), fn ($o) => in_array($o['value'], ['phone', 'email', 'whatsapp', 'video', 'in_person'], true))),
                'chatEnabled' => app(ChatSecuritySettings::class)->guestChatEnabled(),
            ],
            'skill_search' => ['countries' => SkillMigrationController::published()->map(fn (array $c) => ['slug' => $c['slug'], 'url' => $c['url'], 'country' => $c['country'], 'iso2' => $c['iso2']])->values()],
            'contact_hero' => ['cards' => array_map(fn (array $card) => $this->contactCard($card), $data['cards'])],
            'visit' => $this->visit($data),
            'partner_form' => [
                'types' => collect(Partner::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'interests' => collect(PartnerApplication::INTERESTS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'years' => collect(PartnerApplication::YEARS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'destinations' => self::destinationOptions(),
                'email' => (string) app(SettingsRepository::class)->get('general.contact_email', ''),
                'phone' => (string) app(SettingsRepository::class)->get('general.phone', ''),
                'countries' => Country::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values(),
            ],
            default => null,
        };
    }

    /**
     * A Contact page card: phone, email and WhatsApp come from Site settings
     * unless the card gives its own title.
     *
     * @param  array<string, mixed>  $card
     * @return array{label: string, title: string, text: string, link_label: string, href: string|null, external: bool}
     */
    private function contactCard(array $card): array
    {
        $settings = app(SettingsRepository::class);
        $phone = (string) $settings->get('general.phone', '');
        $email = (string) $settings->get('general.contact_email', '');
        $whatsapp = preg_replace('/\D/', '', (string) $settings->get('general.whatsapp', '')) ?? '';

        [$title, $href] = match ($card['kind']) {
            'phone' => [$phone, $phone !== '' ? 'tel:'.preg_replace('/\s+/', '', $phone) : null],
            'email' => [$email, $email !== '' ? 'mailto:'.$email : null],
            'whatsapp' => ['', $whatsapp !== '' ? 'https://wa.me/'.$whatsapp : null],
            default => ['', $card['url'] !== '' && SectionSanitizer::safeUrl($card['url']) ? $card['url'] : null],
        };

        return [
            'label' => (string) $card['label'],
            'title' => $card['title'] !== '' ? (string) $card['title'] : $title,
            'text' => (string) $card['text'],
            'link_label' => (string) $card['link_label'],
            'href' => $href,
            'external' => $href !== null && str_starts_with($href, 'http'),
        ];
    }

    /**
     * Address, a map embed and a Google Maps link for the visit section. The
     * embed is Google's when a Maps key is saved in Integrations and there is an
     * address; otherwise OpenStreetMap, from the saved coordinates.
     *
     * @param  array<string, mixed>  $data
     * @return array{address: string, embed: string|null, map_url: string}
     */
    private function visit(array $data): array
    {
        $address = $data['address'] !== '' ? (string) $data['address'] : (string) app(SettingsRepository::class)->get('general.address', '');
        $lat = is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = is_numeric($data['longitude']) ? (float) $data['longitude'] : null;
        $embed = null;
        $mapsKey = trim((string) app(SettingsRepository::class)->get('integrations.google_maps_key', ''));

        if ($mapsKey !== '' && $address !== '') {
            $embed = 'https://www.google.com/maps/embed/v1/place?'.http_build_query(['key' => $mapsKey, 'q' => $address]);
        } elseif ($lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180) {
            // A wide box: the map is about 3.7 times wider than it is tall.
            $bbox = implode(',', [$lng - 0.03, $lat - 0.009, $lng + 0.03, $lat + 0.009]);
            $embed = 'https://www.openstreetmap.org/export/embed.html?'.http_build_query(['bbox' => $bbox, 'layer' => 'mapnik', 'marker' => "{$lat},{$lng}"]);
        }

        $link = $data['map_link']['url'] ?? '';

        return [
            'address' => $address,
            'embed' => $embed,
            'map_url' => $link !== '' ? $link : 'https://www.google.com/maps/search/?'.http_build_query(['api' => 1, 'query' => $address]),
        ];
    }

    /** @return array<string, mixed> */
    private function finder(): array
    {
        return [
            'countries' => self::destinationOptions(),
            'levels' => self::levelOptions(),
            'action' => '/universities',
        ];
    }

    /** @return Collection<int, array{value: string, label: string}> */
    public static function destinationOptions(): Collection
    {
        return Country::destinations()->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values();
    }

    /** @return list<array{value: string, label: string}> */
    public static function levelOptions(): array
    {
        return array_map(fn (StudyLevel $l) => ['value' => $l->value, 'label' => $l->label()], StudyLevel::cases());
    }

    /** @return array<string, mixed> */
    private function faqs(bool $all): array
    {
        $faqs = Faq::query()->where('is_active', true)->when(! $all, fn (Builder $q) => $q->where('show_on_home', true))
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'faq_category_id', 'question', 'answer']);
        $categories = FaqCategory::query()->whereKey($faqs->pluck('faq_category_id')->filter()->unique())->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);

        $groups = $categories->map(fn (FaqCategory $c) => [
            'name' => $c->name,
            'slug' => $c->slug,
            'items' => $faqs->where('faq_category_id', $c->id)->map(fn (Faq $f) => ['id' => $f->id, 'question' => $f->question, 'answer' => $f->answer])->values(),
        ])->values();

        $loose = $faqs->whereNull('faq_category_id');
        if ($loose->isNotEmpty()) {
            $groups->push(['name' => __('General'), 'slug' => 'general', 'items' => $loose->map(fn (Faq $f) => ['id' => $f->id, 'question' => $f->question, 'answer' => $f->answer])->values()]);
        }

        return ['groups' => $groups];
    }

    /** @return array<string, mixed> */
    private function blogListing(): array
    {
        $category = Str::limit((string) $this->request->query('category', ''), 120, '');
        $search = Str::limit(trim((string) $this->request->query('q', '')), 100, '');
        $filtered = $category !== '' || $search !== '';
        $with = ['featuredImage', 'category:id,name,slug', 'tags:id,name'];

        // The newest featured article leads the unfiltered first page, and isn't repeated below it.
        $featured = ! $filtered && $this->request->integer('page', 1) <= 1
            ? BlogPost::query()->live()->where('is_featured', true)->with($with)->latest('published_at')->first()
            : null;
        $featuredId = BlogPost::query()->live()->where('is_featured', true)->latest('published_at')->value('id');

        $posts = BlogPost::query()->live()->with($with)
            ->when(! $filtered && $featuredId, fn (Builder $q) => $q->whereKeyNot($featuredId))
            ->when($category !== '', fn (Builder $q) => $q->whereHas('category', fn (Builder $c) => $c->where('slug', $category)))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('title', 'like', '%'.self::like($search).'%')->orWhere('excerpt', 'like', '%'.self::like($search).'%')))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(9)->withQueryString();

        return [
            'featured' => $featured ? self::post($featured) : null,
            'items' => collect($posts->items())->map(fn (BlogPost $p) => self::post($p))->values(),
            'pagination' => self::pagination($posts),
            'categories' => BlogCategory::query()->where('is_active', true)->whereHas('posts', fn (Builder $q) => $q->live())
                ->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']),
            'filters' => ['category' => $category, 'q' => $search],
        ];
    }

    /** @return array<string, mixed> */
    private function universityListing(): array
    {
        $country = (int) $this->request->query('country', 0);
        $level = StudyLevel::tryFrom((string) $this->request->query('level', ''));
        $search = Str::limit(trim((string) $this->request->query('q', '')), 100, '');

        $courseFilter = fn (Builder $q) => $q->where('is_active', true)
            ->when($level, fn (Builder $c) => $c->where('level', $level->value))
            ->when($search !== '', fn (Builder $c) => $c->where(fn (Builder $w) => $w->where('name', 'like', '%'.self::like($search).'%')->orWhere('subject', 'like', '%'.self::like($search).'%')));

        $universities = University::query()->active()->with(['logo', 'banner', 'country:id,name'])
            ->when($country > 0, fn (Builder $q) => $q->where('country_id', $country))
            ->when($level, fn (Builder $q) => $q->whereHas('courses', $courseFilter))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('name', 'like', '%'.self::like($search).'%')->orWhere('city', 'like', '%'.self::like($search).'%')->orWhereHas('courses', $courseFilter)))
            ->withCount(['courses' => $courseFilter])
            ->with(['courses' => fn ($q) => $courseFilter($q->getQuery())->orderBy('name')->select(['id', 'university_id', 'name', 'slug', 'level', 'duration'])])
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')
            ->paginate(12)->withQueryString();

        return [
            'items' => collect($universities->items())->map(fn (University $u) => [
                ...self::university($u),
                // A few matching courses so the finder answers "what can I study there?".
                'matching' => $u->courses->take(3)->map(fn (Course $c) => ['name' => $c->name, 'level' => $c->level->label(), 'duration' => $c->duration])->values(),
            ])->values(),
            'pagination' => self::pagination($universities),
            'countries' => self::destinationOptions(),
            'levels' => self::levelOptions(),
            'filters' => ['country' => $country > 0 ? (string) $country : '', 'level' => $level->value ?? '', 'q' => $search],
        ];
    }

    /** @param LengthAwarePaginator<int, mixed> $paginator
     * @return array{current: int, last: int, total: int, prev: string|null, next: string|null, pages: list<array{number: int, url: string}>}
     */
    public static function pagination(LengthAwarePaginator $paginator): array
    {
        $current = $paginator->currentPage();
        $numbers = range(max(1, $current - 2), min($paginator->lastPage(), $current + 2));

        return [
            'current' => $current,
            'last' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
            'pages' => array_map(fn (int $n) => ['number' => $n, 'url' => $paginator->url($n)], $numbers),
        ];
    }

    private static function like(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /** @return array<string, mixed> */
    public static function post(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'url' => '/blog/'.$post->slug,
            'excerpt' => (string) $post->excerpt,
            'image' => self::image($post->featuredImage),
            'category' => $post->category ? ['name' => $post->category->name, 'slug' => $post->category->slug] : null,
            // Cards show the first tag (e.g. a destination) or else the category, as in Figma.
            'label' => $post->relationLoaded('tags') && $post->tags->isNotEmpty() ? $post->tags->first()->name : $post->category?->name,
            'date' => $post->published_at?->format('d M Y'),
            'iso_date' => $post->published_at?->toIso8601String(),
            'reading_minutes' => $post->reading_minutes,
        ];
    }

    /** @return array<string, mixed> */
    public static function testimonial(Testimonial $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'role' => $t->role,
            'country' => $t->country?->name,
            'quote' => $t->quote,
            'rating' => $t->rating,
            'photo' => self::image($t->photo),
        ];
    }

    /** @return array<string, mixed> */
    public static function university(University $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'url' => '/universities/'.$u->slug,
            'city' => $u->city,
            'country' => $u->country->name,
            'summary' => (string) $u->summary,
            'logo' => self::image($u->logo),
            'banner' => self::image($u->banner),
            'courses' => (int) ($u->courses_count ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public static function course(Course $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'level' => $c->level->label(),
            'subject' => $c->subject,
            'duration' => $c->duration,
            'tuition' => $c->tuition_fee ? trim(($c->tuition_currency ?? '').' '.$c->tuition_fee.($c->tuition_period ? ' / '.$c->tuition_period : '')) : null,
            'university' => ['name' => $c->university->name, 'url' => '/universities/'.$c->university->slug, 'country' => $c->university->country->name],
        ];
    }
}
