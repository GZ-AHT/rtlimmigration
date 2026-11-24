<?php

use App\Enums\PublishStatus;
use App\Enums\Role;
use App\Models\BlogPost;
use App\Models\BrokenLink;
use App\Models\Country;
use App\Models\Media;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\University;
use App\Support\Seo\LinkScanner;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/* Phase 10: SEO management (REQ §22). */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->seed([SettingsSeeder::class, ReferenceDataSeeder::class, WebsiteContentSeeder::class]);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
});

function seoSetting(string $key, mixed $value): void
{
    app(SettingsRepository::class)->set("seo.{$key}", $value, public: true);
}

function liveHome(): Page
{
    return Page::where('system_key', 'home')->firstOrFail();
}

/* ---------------------------------------------------------- sitemap, robots */

it('lists live public pages, posts and universities in the sitemap, and leaves out hidden ones', function () {
    BlogPost::create(['title' => 'Live post', 'slug' => 'live-post', 'content' => '<p>x</p>', 'status' => PublishStatus::Published, 'published_at' => now()->subHour()]);
    BlogPost::create(['title' => 'Draft post', 'slug' => 'draft-post', 'content' => '<p>x</p>', 'status' => PublishStatus::Draft]);
    $hidden = BlogPost::create(['title' => 'Hidden post', 'slug' => 'hidden-post', 'content' => '<p>x</p>', 'status' => PublishStatus::Published, 'published_at' => now()->subHour()]);
    $hidden->saveSeo(['noindex' => true]);
    University::create(['name' => 'Maple University', 'slug' => 'maple-university', 'country_id' => Country::where('iso2', 'CA')->value('id'), 'is_active' => true]);
    University::create(['name' => 'Closed University', 'slug' => 'closed-university', 'country_id' => Country::where('iso2', 'CA')->value('id'), 'is_active' => false]);
    seoSetting('sitemap_extra', "/special-offer\nhttps://example.com/partner");

    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = collect(['pages', 'posts', 'universities', 'more'])->map(fn (string $type) => $this->get("/sitemap-{$type}.xml")->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent())->implode("\n");

    expect($xml)->toContain('<loc>'.url('/').'</loc>')
        ->toContain(url('/faq'))->toContain(url('/blog/live-post'))->toContain(url('/universities/maple-university'))
        ->toContain(url('/special-offer'))->toContain('https://example.com/partner')
        ->not->toContain('draft-post')->not->toContain('hidden-post')->not->toContain('closed-university');
});

it('splits the sitemap into one file per kind of content, listed by an index', function () {
    BlogPost::create(['title' => 'Live post', 'slug' => 'live-post', 'content' => '<p>x</p>', 'status' => PublishStatus::Published, 'published_at' => now()->subHour()]);
    seoSetting('sitemap_extra', '/special-offer');

    $index = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($index)->toContain('<sitemapindex')->not->toContain('<urlset')
        ->toContain('<loc>'.url('/sitemap-pages.xml').'</loc>')->toContain('<loc>'.url('/sitemap-posts.xml').'</loc>')->toContain('<loc>'.url('/sitemap-more.xml').'</loc>')
        ->not->toContain('sitemap-universities.xml')  // nothing to list, so no file
        ->not->toContain('sitemap-assessments.xml');

    $posts = $this->get('/sitemap-posts.xml')->assertOk()->getContent();
    expect($posts)->toContain('<urlset')->toContain(url('/blog/live-post'))->not->toContain(url('/faq'));
    expect($this->get('/sitemap-pages.xml')->getContent())->toContain(url('/faq'))->not->toContain('live-post');
    expect($this->get('/sitemap-more.xml')->getContent())->toContain(url('/special-offer'));

    // Empty or unknown kinds are not files.
    $this->get('/sitemap-universities.xml')->assertNotFound();
    $this->get('/sitemap-nonsense.xml')->assertNotFound();
});

it('lists an address in one sitemap only', function () {
    seoSetting('sitemap_extra', '/faq
/brand-new');

    expect($this->get('/sitemap-pages.xml')->getContent())->toContain(url('/faq'));
    expect($this->get('/sitemap-more.xml')->getContent())->toContain(url('/brand-new'))->not->toContain(url('/faq'));
});

it('turns the sitemap off, and hides everything when the site is set to noindex', function () {
    seoSetting('sitemap_enabled', false);
    $this->get('/sitemap.xml')->assertNotFound();
    $this->get('/sitemap-pages.xml')->assertNotFound();

    seoSetting('sitemap_enabled', true);
    seoSetting('noindex_site', true);
    $this->get('/sitemap.xml')->assertNotFound();
    $this->get('/sitemap-pages.xml')->assertNotFound();
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
});

it('serves a default robots.txt pointing at the sitemap, or the one the admin wrote', function () {
    $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin', false)->assertSee('Sitemap: '.url('/sitemap.xml'), false);

    seoSetting('robots_txt', "User-agent: *\nDisallow: /private");
    expect($this->get('/robots.txt')->getContent())->toContain('Disallow: /private')->not->toContain('Disallow: /admin');
});

/* ------------------------------------------------------------ page head data */

it('uses the global defaults, robots meta and structured data on the homepage', function () {
    app(SettingsRepository::class)->set('general.address', 'Gulshan 1, Dhaka', public: true);
    seoSetting('default_title', 'RTL Immigration | Study abroad');
    seoSetting('default_keywords', 'study abroad, visa');
    seoSetting('twitter_site', '@rtlbd');
    seoSetting('publisher', 'RTBL Group');

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('seo.title', 'RTL Immigration | Study abroad')
        ->where('seo.keywords', 'study abroad, visa')
        ->where('seo.twitter_site', '@rtlbd')
        ->where('seo.robots', 'index, follow, max-image-preview:large')
        ->where('seo.hreflang', 'en')
        ->where('seo.schema', fn ($schema) => collect($schema)->pluck('@type')->all() === ['Organization', 'WebSite', 'ProfessionalService']));

    $this->get('/')->assertSee('application/ld+json', false)->assertSee('"@type":"Organization"', false);
});

it('adds breadcrumb and article data to a blog post, and a page can bring its own structured data', function () {
    $post = BlogPost::create(['title' => 'Study in Denmark', 'slug' => 'study-denmark', 'content' => '<p>x</p>', 'status' => PublishStatus::Published, 'published_at' => now()->subDay()]);
    $post->saveSeo(['custom_schema' => json_encode(['@type' => 'FAQPage', 'mainEntity' => []]), 'meta_keywords' => 'denmark', 'twitter_card' => 'summary']);

    $this->get('/blog/study-denmark')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('seo.twitter_card', 'summary')
        ->where('seo.keywords', 'denmark')
        ->where('seo.schema', function ($schema) {
            $schema = collect($schema);
            $crumbs = $schema->firstWhere('@type', 'BreadcrumbList');

            return $schema->pluck('@type')->all() === ['BreadcrumbList', 'Article', 'FAQPage']
                && collect($crumbs['itemListElement'])->pluck('name')->all() === ['Home', 'Blog', 'Study in Denmark']
                && $schema->firstWhere('@type', 'Article')['headline'] === 'Study in Denmark';
        }));
});

it('noindex and nofollow settings reach the robots meta, and a page can switch its own SEO off', function () {
    $post = BlogPost::create(['title' => 'Private note', 'slug' => 'private-note', 'content' => '<p>x</p>', 'status' => PublishStatus::Published, 'published_at' => now()->subDay()]);
    $post->saveSeo(['noindex' => true, 'nofollow' => true, 'meta_title' => 'Custom']);

    $this->get('/blog/private-note')->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex, nofollow')->where('seo.schema', []));

    $post->saveSeo(['seo_enabled' => false, 'meta_title' => 'Ignored']);
    $this->get('/blog/private-note')->assertInertia(fn (Assert $page) => $page->where('seo.title', 'Private note | RTL Bangladesh')->where('seo.robots', 'index, follow, max-image-preview:large'));

    seoSetting('default_robots', 'noindex,follow');
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex, follow'));
});

it('puts verification tags and analytics in the page, but only when the codes are valid', function () {
    seoSetting('google_verification', 'abc123');
    seoSetting('ga_id', 'G-ABC12345');
    seoSetting('gtm_id', 'GTM-ABCD123');

    $this->get('/')->assertSee('name="google-site-verification" content="abc123"', false)
        ->assertSee('googletagmanager.com/gtag/js?id=G-ABC12345', false)->assertSee("'dataLayer','GTM-ABCD123'", false);

    seoSetting('ga_id', "G-X');alert(1);//");
    $this->get('/')->assertDontSee('gtag/js?id=G-X', false)->assertDontSee("gtag('config','G-X", false);

    seoSetting('enabled', false);
    $this->get('/')->assertDontSee('google-site-verification', false)->assertDontSee('application/ld+json', false);
});

it('validates the SEO settings and the custom structured data', function () {
    $this->actingAs($this->admin)->get(route('admin.seo.settings'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('group.fields.sitemap_enabled')->where('values.enabled', true));

    $this->actingAs($this->admin)->get(route('admin.cms.settings'))->assertInertia(fn (Assert $page) => $page->missing('groups.seo'));
    $this->actingAs($this->admin)->put(route('admin.cms.settings.update', 'seo'), ['enabled' => false])->assertNotFound();

    $this->actingAs($this->admin)->put(route('admin.seo.settings.update'), ['ga_id' => 'UA-1', 'gtm_id' => '<b>', 'google_verification' => 'a b'])
        ->assertSessionHasErrors(['ga_id', 'gtm_id', 'google_verification']);

    $this->actingAs($this->admin)->put(route('admin.seo.settings.update'), ['enabled' => true, 'ga_id' => 'G-ABC12345', 'robots_txt' => "User-agent: *\nDisallow:"])
        ->assertSessionHasNoErrors();
    expect(app(SettingsRepository::class)->get('seo.ga_id'))->toBe('G-ABC12345');

    $page = Page::where('slug', 'faq')->firstOrFail();
    $payload = ['title' => $page->title, 'slug' => 'faq', 'status' => 'published', 'visibility' => 'public', 'sections' => $page->sections];
    $this->actingAs($this->admin)->put(route('admin.pages.update', $page), [...$payload, 'seo' => ['custom_schema' => '{not json']])
        ->assertSessionHasErrors('seo.custom_schema');
});

/* ---------------------------------------------------------------- redirects */

it('redirects an old path with its status code, keeps the query string, and counts hits', function () {
    Redirect::create(['from_path' => '/old-page', 'to_url' => '/faq', 'status_code' => 301]);
    Redirect::create(['from_path' => '/promo', 'to_url' => 'https://example.com/offer', 'status_code' => 302]);
    Redirect::create(['from_path' => '/off', 'to_url' => '/faq', 'status_code' => 301, 'is_active' => false]);

    $this->get('/OLD-page/?utm=x')->assertStatus(301)->assertRedirect('/faq?utm=x');
    $this->get('/promo')->assertStatus(302)->assertRedirect('https://example.com/offer');
    $this->get('/off')->assertNotFound();
    $this->post('/old-page')->assertStatus(405);

    expect(Redirect::where('from_path', '/old-page')->value('hits'))->toBe(1);
});

it('manages redirects in the admin, and refuses loops, duplicates and protected paths', function () {
    $this->actingAs($this->admin)->get(route('admin.seo.redirects.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/seo/Redirects')->has('redirects.data', 0));

    $this->actingAs($this->admin)->post(route('admin.seo.redirects.store'), ['from_path' => 'Old-Page/', 'to_url' => '/new-page', 'status_code' => 301])->assertSessionHasNoErrors();
    expect(Redirect::first()->from_path)->toBe('/old-page');

    $this->actingAs($this->admin)->post(route('admin.seo.redirects.store'), ['from_path' => '/old-page', 'to_url' => '/x', 'status_code' => 301])->assertSessionHasErrors('from_path');
    $this->actingAs($this->admin)->post(route('admin.seo.redirects.store'), ['from_path' => '/new-page', 'to_url' => '/old-page', 'status_code' => 301])->assertSessionHasErrors('to_url');
    $this->actingAs($this->admin)->post(route('admin.seo.redirects.store'), ['from_path' => '/admin/users', 'to_url' => '/', 'status_code' => 301])->assertSessionHasErrors('from_path');
    $this->actingAs($this->admin)->post(route('admin.seo.redirects.store'), ['from_path' => '/a', 'to_url' => 'javascript:alert(1)', 'status_code' => 301])->assertSessionHasErrors('to_url');
    $this->actingAs($this->admin)->post(route('admin.seo.redirects.store'), ['from_path' => '/b', 'to_url' => '/c', 'status_code' => 307])->assertSessionHasErrors('status_code');

    $redirect = Redirect::first();
    $this->actingAs($this->admin)->put(route('admin.seo.redirects.update', $redirect), ['from_path' => '/old-page', 'to_url' => '/contact', 'status_code' => 302, 'is_active' => false])->assertSessionHasNoErrors();
    expect($redirect->fresh())->status_code->toBe(302)->is_active->toBeFalse();
    $this->get('/old-page')->assertNotFound();

    $this->actingAs($this->admin)->delete(route('admin.seo.redirects.destroy', $redirect))->assertRedirect();
    expect(Redirect::count())->toBe(0);
});

it('keeps SEO management to people with the SEO permission', function () {
    $counselor = $this->userWithRole(Role::Counselor);

    $this->actingAs($counselor)->get(route('admin.seo.redirects.index'))->assertForbidden();
    $this->actingAs($counselor)->get(route('admin.seo.broken-links.index'))->assertForbidden();
    $this->actingAs($counselor)->post(route('admin.seo.broken-links.scan'))->assertForbidden();
});

/* ------------------------------------------------------------ broken links */

it('finds broken links in pages, posts and menus and replaces the previous results', function () {
    Http::fake([
        '*/gone' => Http::response('', 404),
        '*/ok' => Http::response('', 200),
        'https://dead.example.com/*' => Http::response('', 410),
        'https://fine.example.com/*' => Http::response('', 200),
    ]);
    BrokenLink::create(['source_type' => 'page', 'source_label' => 'Stale', 'target_url' => '/stale', 'checked_at' => now()->subWeek()]);

    $page = Page::where('slug', 'faq')->firstOrFail();
    $page->update(['sections' => [
        ['id' => 'a', 'type' => 'rich_text', 'enabled' => true, 'data' => ['content' => '<p><a href="/gone">x</a> <a href="https://fine.example.com/a">y</a> <a href="mailto:a@b.c">z</a></p>', 'url' => '/ok'], 'settings' => []],
    ]]);
    BlogPost::create(['title' => 'Linky', 'slug' => 'linky', 'content' => '<a href="https://dead.example.com/page">dead</a>', 'status' => PublishStatus::Published, 'published_at' => now()->subDay()]);

    $count = app(LinkScanner::class)->run();

    expect($count)->toBeGreaterThanOrEqual(2);
    $targets = BrokenLink::pluck('target_url')->all();
    expect($targets)->toContain('/gone')->toContain('https://dead.example.com/page')
        ->not->toContain('/stale')->not->toContain('/ok')->not->toContain('https://fine.example.com/a');
    expect(BrokenLink::where('target_url', '/gone')->first())->source_label->toBe($page->title)->source_url->toBe('/faq')->status_code->toBe(404);

    $this->actingAs($this->admin)->get(route('admin.seo.broken-links.index'))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/seo/BrokenLinks')->where('links', fn ($l) => count($l) === $count));
});

it('runs the scan from the command line', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $this->artisan('seo:check-links')->assertSuccessful();
    expect(BrokenLink::count())->toBe(0);
});

/* --------------------------------------------------- alt text, pagination */

it('finds images without alt text in the media library', function () {
    $with = Media::create(['disk' => 'public', 'path' => 'media/a.jpg', 'original_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'alt' => 'A campus']);
    $without = Media::create(['disk' => 'public', 'path' => 'media/b.jpg', 'original_name' => 'b.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
    $video = Media::create(['disk' => 'public', 'path' => 'media/c.mp4', 'original_name' => 'c.mp4', 'mime_type' => 'video/mp4', 'size' => 10]);

    $this->actingAs($this->admin)->get(route('admin.media.index', ['missing_alt' => 1]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.missing_alt', '1')->where('media.data', fn ($d) => collect($d)->pluck('id')->contains($without->id) && ! collect($d)->pluck('id')->contains($with->id) && ! collect($d)->pluck('id')->contains($video->id)));

    $this->actingAs($this->admin)->get(route('admin.media.index'))->assertInertia(fn (Assert $page) => $page->where('filters.missing_alt', '')->where('media.data', fn ($d) => collect($d)->pluck('id')->contains($with->id)));
});

it('gives page two of a list its own canonical address', function () {
    $this->get('/blog?page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->where('seo.canonical', url('/blog').'?page=2'));
    $this->get('/blog?page=1')->assertInertia(fn (Assert $page) => $page->where('seo.canonical', url('/blog')));
});
