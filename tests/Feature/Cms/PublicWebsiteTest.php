<?php

use App\Enums\CommentStatus;
use App\Enums\PublishStatus;
use App\Enums\Role;
use App\Enums\StudyLevel;
use App\Enums\VisaKind;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Country;
use App\Models\Course;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\PartnerApplication;
use App\Models\Testimonial;
use App\Models\University;
use App\Notifications\PartnerApplicationReceived;
use App\Support\Cms\SectionSanitizer;
use App\Support\Cms\SectionTypes;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\DemoReviewsSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/* Phase 9b: the public website built from CMS pages (REQ §4, §5, §15, §16, §21). */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed([SettingsSeeder::class, ReferenceDataSeeder::class, WebsiteContentSeeder::class]);
});

function cmsPage(string $slug, array $sections, array $attributes = []): Page
{
    return Page::create([
        'title' => ucfirst($slug), 'slug' => $slug, 'status' => PublishStatus::Published, 'visibility' => 'public',
        'sections' => app(SectionSanitizer::class)->clean($sections), ...$attributes,
    ]);
}

function partnerApplication(array $overrides = []): array
{
    return [
        'organisation' => 'Bright Path Agency', 'type' => 'agency', 'country_id' => Country::where('iso2', 'BD')->value('id'), 'city' => 'Dhaka',
        'contact_name' => 'Nusrat Jahan', 'job_title' => 'Director', 'email' => 'hello@brightpath.test', 'phone' => '+880 1711 000000',
        'partnership_interest' => 'recruitment', 'years_in_operation' => '3-5',
        'destinations' => [Country::where('iso2', 'CA')->value('id'), Country::where('iso2', 'AU')->value('id')],
        'message' => 'We place 40 students a year in Canada and Australia.', 'consent' => true,
        ...$overrides,
    ];
}

function university(array $attributes = []): University
{
    return University::create(['name' => 'Maple University', 'slug' => 'maple-university', 'country_id' => Country::where('iso2', 'CA')->value('id'), 'is_active' => true, ...$attributes]);
}

/* ------------------------------------------------------------- seeding */

it('seeds the built-in pages, menus and Figma images once', function () {
    $counts = fn () => [Page::count(), MenuItem::count(), Media::count(), BlogPost::count()];
    $first = $counts();

    $this->seed(WebsiteContentSeeder::class);

    expect($counts())->toBe($first)
        ->and(Page::whereNotNull('system_key')->pluck('system_key')->all())->toContain('home', 'about', 'contact', 'faq', 'blog', 'universities', 'reviews', 'partners', 'privacy')
        ->and(Media::count())->toBe(15);

    Storage::disk('public')->assertExists(Media::first()->path);
});

it('does not overwrite a page an admin has edited', function () {
    Page::where('system_key', 'about')->update(['title' => 'Our team']);

    $this->seed(WebsiteContentSeeder::class);

    expect(Page::where('system_key', 'about')->value('title'))->toBe('Our team');
});

/* --------------------------------------------------------------- pages */

it('renders the homepage from its sections with live records and menus', function () {
    $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('website/Page')
        ->where('sections.0.type', 'hero')
        ->where('sections.0.data.title', 'Your future.')
        ->has('sections.0.records.countries', 7)
        ->has('sections.0.records.levels', count(StudyLevel::cases()))
        ->where('sections.0.data.image.alt', 'Student outside a modern campus building')
        ->has('sections.6.records.items', 1) // featured testimonial
        ->has('sections.7.records.items', 3) // latest posts
        ->where('menus.main.1.label', 'Visa services')
        ->has('menus.main.1.children', 5)
        ->where('seo.title', 'RTL Bangladesh | Immigration & Visa Advisory'));
});

it('renders every built-in and service page', function (string $path) {
    $this->get($path)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('website/Page'));
})->with(['/about-us', '/contact', '/faq', '/blog', '/universities', '/reviews', '/become-a-partner', '/student-visa', '/work-permits', '/privacy-policy']);

it('serves the homepage only at the root', function () {
    $this->get('/home')->assertRedirect('/');
});

it('drops hidden sections and keeps only public fields', function () {
    cmsPage('offers', [
        ['type' => 'cta', 'enabled' => false, 'data' => ['title' => 'Hidden']],
        ['type' => 'cta', 'data' => ['title' => 'Visible', 'button' => ['label' => 'Go', 'url' => '/contact']]],
    ]);

    $this->get('/offers')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sections', 1)
        ->where('sections.0.data.title', 'Visible'));
});

it('hides drafts and scheduled pages from visitors but previews them for editors', function () {
    cmsPage('draft-page', [['type' => 'rich_text', 'data' => ['content' => '<p>Soon</p>']]], ['status' => PublishStatus::Draft]);
    cmsPage('later', [], ['published_at' => now()->addDay()]);

    $this->get('/draft-page')->assertNotFound()->assertInertia(fn (AssertableInertia $page) => $page->component('website/Error')->where('status', 404));
    $this->get('/later')->assertNotFound();

    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get('/later')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('page.preview', 'scheduled')->where('seo.noindex', true));
});

it('sends visitors to log in for members-only pages', function () {
    cmsPage('members', [], ['visibility' => 'members']);

    $this->get('/members')->assertRedirect(route('login'));
    $this->actingAs($this->userWithRole(Role::Student))->get('/members')->assertOk();
});

it('shows the website 404 page for unknown addresses', function () {
    $this->get('/no-such-page')->assertNotFound()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('website/Error')
        ->has('menus.main'));
});

it('adds site-wide custom code to website pages only and keeps them light', function () {
    $settings = app(SettingsRepository::class);
    $settings->set('code.head_js', '<script>window.siteTag = 1;</script>');
    $settings->set('code.css', 'body{color:red}</style><script>x</script>');

    $html = $this->withCookie('appearance', 'dark')->get('/')->assertOk()->getContent();

    expect($html)->toContain('window.siteTag = 1;')
        ->toContain('body{color:red}>')
        ->not->toContain('</style><script>x')
        ->not->toMatch('/<html[^>]*class="[^"]*dark/');

    $admin = $this->actingAs($this->userWithRole(Role::SuperAdmin))->get(route('admin.dashboard'))->getContent();
    expect($admin)->not->toContain('window.siteTag');
});

it('builds the Figma contact page with cards and a map from the site settings', function () {
    app(SettingsRepository::class)->set('general.phone', '+880 1700 000000', public: true);

    $this->get('/contact')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.type', 'contact_hero')
        ->where('sections.0.records.cards.0.title', '+880 1700 000000')
        ->where('sections.0.records.cards.0.href', 'tel:+8801700000000')
        ->where('sections.0.records.cards.1.href', 'mailto:info@rtlbangladesh.com')
        ->where('sections.0.records.cards.2.title', 'A conversation, your way.')
        ->where('sections.0.records.cards.2.external', true)
        ->where('sections.1.settings.spacing', 'joined')
        ->where('sections.2.type', 'visit')
        ->where('sections.2.records.address', 'Gulshan 1, Dhaka, Bangladesh')
        ->where('sections.2.records.embed', fn (string $url) => str_starts_with($url, 'https://www.openstreetmap.org/export/embed.html?') && str_contains($url, 'marker=23.7806'))
        ->where('sections.2.records.map_url', fn (string $url) => str_starts_with($url, 'https://www.google.com/maps/search/')));
});

it('shows a Google map on the contact page once a Maps key is saved, and falls back without one', function () {
    $embed = fn () => $this->get('/contact')->assertOk()->viewData('page')['props']['sections'][2]['records']['embed'];
    expect($embed())->toStartWith('https://www.openstreetmap.org/');

    app(SettingsRepository::class)->set('integrations.google_maps_key', 'AIzaSyExampleKey_1234567890abcd', public: true);
    expect($embed())->toStartWith('https://www.google.com/maps/embed/v1/place?key=AIzaSyExampleKey_1234567890abcd&q=Gulshan')
        ->toContain('Dhaka');

    app(SettingsRepository::class)->set('integrations.google_maps_key', '', public: true);
    expect($embed())->toStartWith('https://www.openstreetmap.org/');
});

it('upgrades an untouched form-only contact page, but not an edited one', function () {
    $contact = Page::where('system_key', 'contact')->first();
    $form = collect($contact->sections)->firstWhere('type', 'contact');
    $contact->update(['sections' => [$form]]);

    $this->seed(WebsiteContentSeeder::class);
    expect(collect($contact->fresh()->sections)->pluck('type')->all())->toBe(['contact_hero', 'contact', 'visit']);

    $contact->update(['sections' => [$form, $form]]);
    $this->seed(WebsiteContentSeeder::class);
    expect($contact->fresh()->sections)->toHaveCount(2);
});

it('leaves the map out when the coordinates are not valid', function () {
    cmsPage('visit-us', [['type' => 'visit', 'data' => ['latitude' => 'abc', 'longitude' => '200']]]);

    $this->get('/visit-us')->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.records.embed', null));
});

/* --------------------------------------------------------------- menus */

it('shows menu links by visibility and hides links to unpublished pages', function () {
    $menu = Menu::where('location', 'footer')->first();
    $menu->items()->create(['label' => 'Guests only', 'link_type' => 'url', 'url' => '/register', 'visibility' => 'guests', 'sort_order' => 1]);
    $menu->items()->create(['label' => 'Accounts team', 'link_type' => 'url', 'url' => '/x', 'visibility' => 'roles', 'roles' => ['accounts'], 'sort_order' => 2]);
    Page::where('system_key', 'reviews')->update(['status' => PublishStatus::Draft]);

    $labels = fn ($response) => collect($response->inertiaProps('menus.footer'))->pluck('label');

    expect($labels($this->get('/about-us')))->toContain('Guests only')->not->toContain('Accounts team', 'Student reviews');
    expect($labels($this->actingAs($this->userWithRole(Role::Accounts))->get('/about-us')))->toContain('Accounts team')->not->toContain('Guests only');
});

/* ---------------------------------------------------------------- blog */

it('lists posts with category and search filters', function () {
    $news = BlogCategory::create(['name' => 'News', 'slug' => 'news', 'is_active' => true]);
    BlogPost::create(['title' => 'Intake dates announced', 'slug' => 'intake-dates', 'content' => '<p>x</p>', 'blog_category_id' => $news->id, 'status' => PublishStatus::Published, 'published_at' => now()->subHour()]);
    BlogPost::create(['title' => 'Draft post', 'slug' => 'draft-post', 'content' => '<p>x</p>', 'status' => PublishStatus::Draft]);

    $this->get('/blog?category=news')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sections.1.records.items', 1)
        ->where('sections.1.records.items.0.title', 'Intake dates announced')
        ->where('sections.1.records.filters.category', 'news'));

    $this->get('/blog?q=denmark')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sections.1.records.items', 1)
        ->where('sections.1.records.pagination.total', 1));
});

it('shows a published article, counts one view per visit and hides drafts', function () {
    $post = BlogPost::where('slug', 'study-in-armenia-from-bangladesh')->first();

    $this->get('/blog/'.$post->slug)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('website/BlogPost')
        ->where('post.title', 'Study in Armenia from Bangladesh')
        ->where('seo.type', 'article')
        ->has('related', 3)
        ->where('related.0.label', fn (string $label) => in_array($label, ['Denmark', 'New Zealand'], true)));
    $this->get('/blog/'.$post->slug);

    expect($post->fresh()->views)->toBe(1);

    $post->update(['status' => PublishStatus::Draft]);
    $this->get('/blog/'.$post->slug)->assertNotFound();
});

it('holds new comments for moderation and ignores bots', function () {
    $post = BlogPost::first();

    $this->post(route('blog.comments.store', $post->slug), ['name' => 'Rafi', 'email' => 'rafi@example.com', 'body' => 'Very helpful, thank you.'])->assertRedirect();
    $this->post(route('blog.comments.store', $post->slug), ['name' => 'Bot', 'email' => 'bot@example.com', 'body' => 'spam', 'website' => 'http://spam.test']);

    $comment = BlogComment::sole();
    expect($comment->status)->toBe(CommentStatus::Pending)->and($comment->name)->toBe('Rafi');

    $this->get('/blog/'.$post->slug)->assertInertia(fn (AssertableInertia $page) => $page->has('comments', 0));

    $post->update(['allow_comments' => false]);
    $this->post(route('blog.comments.store', $post->slug), ['name' => 'A', 'email' => 'a@example.com', 'body' => 'Hello there'])->assertForbidden();
});

it('leads the blog with the featured article and labels cards by their first tag', function () {
    $this->get('/blog')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.type', 'page_intro')
        ->where('sections.0.data.aside.label', 'IDEAS FOR YOUR NEXT CHAPTER.')
        ->where('sections.1.records.featured.title', 'Your next chapter starts with a plan.')
        ->where('sections.1.records.featured.label', 'Study abroad')
        ->has('sections.1.records.items', 6) // the featured article isn't repeated
        ->where('sections.1.records.items.0.label', 'Armenia')
        ->where('sections.1.records.items.3.label', 'Planning your journey') // no tag: the category
        ->has('sections.1.records.pagination.pages', 1)
        ->has('sections.1.records.categories', 4)
        ->where('sections.2.data.note', "By subscribing, you agree to our Privacy Policy.\nYou can unsubscribe at any time."));

    // Filtering hides the featured block and searches every article.
    $this->get('/blog?category=planning-your-journey')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.1.records.featured', null)
        ->has('sections.1.records.items', 2));
});

it('limits the homepage articles to the chosen category, as in Figma', function () {
    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.7.records.items.0.title', 'Study in Armenia from Bangladesh')
        ->where('sections.7.records.items.2.title', 'Planning to study in New Zealand?'));
});

it('switches an unchanged first-version blog page to the Figma layout', function () {
    $blog = Page::where('system_key', 'blog')->first();
    $blog->update(['sections' => app(SectionSanitizer::class)->clean([
        ['type' => 'page_hero', 'data' => ['eyebrow' => 'INSIGHTS FOR YOUR NEXT STEP', 'title' => 'Old']],
        ['type' => 'blog', 'data' => ['all' => true]],
        ['type' => 'newsletter', 'data' => []],
    ])]);

    $this->seed(WebsiteContentSeeder::class);

    expect(array_column($blog->fresh()->sections, 'type'))->toBe(['page_intro', 'blog', 'newsletter']);
});

/* -------------------------------------------------------- universities */

it('finds universities by destination and study level (pathway finder)', function () {
    $maple = university();
    Course::create(['university_id' => $maple->id, 'name' => 'MBA', 'slug' => 'mba', 'level' => StudyLevel::Master, 'visa_kind' => VisaKind::Study, 'is_active' => true]);
    $oak = university(['name' => 'Oak College', 'slug' => 'oak-college', 'country_id' => Country::where('iso2', 'AU')->value('id')]);
    Course::create(['university_id' => $oak->id, 'name' => 'Diploma of IT', 'slug' => 'diploma-it', 'level' => StudyLevel::Diploma, 'visa_kind' => VisaKind::Study, 'is_active' => true]);
    university(['name' => 'Closed University', 'slug' => 'closed', 'is_active' => false]);

    $this->get('/universities?level=master')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sections.1.records.items', 1)
        ->where('sections.1.records.items.0.name', 'Maple University')
        ->where('sections.1.records.items.0.matching.0.name', 'MBA'));

    $this->get('/universities?country='.$oak->country_id)->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sections.1.records.items', 1)
        ->where('sections.1.records.items.0.name', 'Oak College'));

    $this->get('/universities')->assertInertia(fn (AssertableInertia $page) => $page->where('sections.1.records.pagination.total', 2));
});

it('shows a university profile with its active courses only', function () {
    $maple = university();
    Course::create(['university_id' => $maple->id, 'name' => 'MBA', 'slug' => 'mba', 'level' => StudyLevel::Master, 'visa_kind' => VisaKind::Study, 'is_active' => true, 'intakes' => [1, 9]]);
    Course::create(['university_id' => $maple->id, 'name' => 'Old course', 'slug' => 'old', 'level' => StudyLevel::Master, 'visa_kind' => VisaKind::Study, 'is_active' => false]);

    $this->get('/universities/maple-university')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('website/University')
        ->has('university.courses', 1)
        ->where('university.courses.0.intakes', ['Jan', 'Sep']));

    $maple->update(['is_active' => false]);
    $this->get('/universities/maple-university')->assertNotFound();
});

/* ------------------------------------------------------------ partners */

it('records a partner application with documents and alerts reviewers', function () {
    Notification::fake();
    $reviewer = $this->userWithRole(Role::SuperAdmin);
    $data = partnerApplication(['documents' => [UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf')]]);

    $this->post(route('partners.apply'), $data)->assertRedirect();
    $this->post(route('partners.apply'), $data)->assertRedirect(); // a resubmit doesn't duplicate it

    $application = PartnerApplication::sole();
    expect($application->status)->toBe('pending')->and($application->documents)->toHaveCount(1)
        ->and($application->job_title)->toBe('Director')
        ->and($application->partnership_interest)->toBe('recruitment')
        ->and($application->destinations)->toBe([Country::where('iso2', 'CA')->value('id'), Country::where('iso2', 'AU')->value('id')]);
    Storage::disk((string) config('documents.disk'))->assertExists($application->documents->first()->path);
    Notification::assertSentTo($reviewer, PartnerApplicationReceived::class);
});

it('rejects unsafe partner documents', function () {
    $this->post(route('partners.apply'), partnerApplication([
        'documents' => [UploadedFile::fake()->create('run.php', 5, 'application/x-php')],
    ]))->assertSessionHasErrors('documents.0');

    expect(PartnerApplication::count())->toBe(0);
});

/* ---------------------------------------------------------- newsletter */

it('subscribes, re-subscribes and unsubscribes newsletter readers', function () {
    $this->post(route('newsletter.store'), ['email' => 'Reader@Example.com'])->assertRedirect();
    $subscriber = NewsletterSubscriber::sole();
    expect($subscriber->email)->toBe('reader@example.com')->and($subscriber->status)->toBe('subscribed');

    $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))->assertRedirect('/');
    expect($subscriber->fresh()->status)->toBe('unsubscribed');

    $this->post(route('newsletter.store'), ['email' => 'reader@example.com']);
    expect($subscriber->fresh()->status)->toBe('subscribed')->and(NewsletterSubscriber::count())->toBe(1);
});

/* ------------------------------------------------- homepage banner designs */

function homeSections(): array
{
    return Page::where('system_key', 'home')->first()->sections;
}

it('seeds the full-width banner hidden and shows only the chosen design', function () {
    expect(array_slice(array_column(homeSections(), 'type'), 0, 2))->toBe(['hero', 'hero_cover'])
        ->and(homeSections()[1]['enabled'])->toBeFalse();

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.type', 'hero')
        ->where('sections.1.type', 'pathways'));

    // Choosing Design 2 hides Design 1 but keeps its content.
    $home = Page::where('system_key', 'home')->first();
    $sections = $home->sections;
    $sections[0]['enabled'] = false;
    $sections[1]['enabled'] = true;
    $home->update(['sections' => $sections]);

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.type', 'hero_cover')
        ->where('sections.0.data.title_accent', 'Without borders.')
        ->where('sections.0.data.overlay', 'gradient')
        ->has('sections.0.records.countries')
        ->where('sections.1.type', 'pathways'));
    expect(homeSections()[0]['data']['title'])->toBe('Your future.');
});

it('shows one banner even if both designs are switched on', function () {
    $home = Page::where('system_key', 'home')->first();
    $sections = $home->sections;
    $sections[1]['enabled'] = true;
    $home->update(['sections' => $sections]);

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.type', 'hero')
        ->where('sections.1.type', 'pathways'));
});

it('lets editors preview the hidden banner design, and nobody else', function () {
    $this->get('/?banner=hero_cover')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.type', 'hero')
        ->where('page.banner_preview', null));

    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get('/?banner=hero_cover')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.type', 'hero_cover')
        ->where('page.banner_preview', 'hero_cover')
        ->where('seo.noindex', true));
});

it('plays a banner video from the media library, with the image as fallback', function () {
    $video = Media::create(['disk' => 'public', 'path' => 'media/clip.mp4', 'original_name' => 'clip.mp4', 'mime_type' => 'video/mp4', 'size' => 1000]);
    $image = Media::where('mime_type', 'like', 'image/%')->first();

    $page = cmsPage('promo', [['type' => 'hero_cover', 'data' => [
        'title' => 'Hi', 'media_type' => 'video', 'video' => $video->id, 'image' => $image->id, 'show_finder' => false,
    ]]]);

    $this->get('/promo')->assertInertia(fn (AssertableInertia $p) => $p
        ->where('sections.0.data.media_type', 'video')
        ->where('sections.0.data.video.mime', 'video/mp4')
        ->where('sections.0.data.image.url', $image->url()));

    // An image field never takes a video, and a video field never takes an image.
    $clean = app(SectionSanitizer::class)->clean([['type' => 'hero', 'data' => ['image' => $video->id, 'video' => $image->id]]]);
    expect($clean[0]['data']['image'])->toBeNull()->and($clean[0]['data']['video'])->toBeNull();
    expect($page->sections[0]['data']['video'])->toBe($video->id);
});

it('accepts MP4 and WebM videos in the media library and refuses other files', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    $this->actingAs($admin)->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->create('clip.mp4', 2000, 'video/mp4')])
        ->assertCreated()->assertJsonPath('kind', 'video');
    $this->actingAs($admin)->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->create('clip.mov', 2000, 'video/quicktime')])
        ->assertUnprocessable();
    $this->actingAs($admin)->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->create('fake.mp4', 10, 'application/x-php')])
        ->assertUnprocessable();
    $this->actingAs($admin)->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->create('big.mp4', 60000, 'video/mp4')])
        ->assertUnprocessable();

    $this->actingAs($admin)->getJson(route('admin.media.index', ['kind' => 'video']))->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson(route('admin.media.index', ['kind' => 'image']))->assertJsonCount(15, 'data');
});

it('adds the second banner design to an existing homepage without changing the rest', function () {
    $home = Page::where('system_key', 'home')->first();
    $without = array_values(array_filter($home->sections, fn ($s) => $s['type'] !== 'hero_cover'));
    $without[0]['data']['title'] = 'Edited title';
    $home->update(['sections' => $without]);

    $this->seed(WebsiteContentSeeder::class);

    $types = array_column(homeSections(), 'type');
    expect(array_slice($types, 0, 2))->toBe(['hero', 'hero_cover'])
        ->and(homeSections()[0]['data']['title'])->toBe('Edited title')
        ->and(homeSections()[1]['enabled'])->toBeFalse()
        ->and(count($types))->toBe(count($without) + 1);
});

it('shows a photo or video in the video counseling call window', function () {
    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.2.type', 'video')
        ->where('sections.2.data.call_style', 'meeting')
        ->where('sections.2.data.media_type', 'image')
        ->where('sections.2.data.adviser_name', 'Your RTL adviser')
        ->where('sections.2.data.show_timer', true));

    $video = Media::create(['disk' => 'public', 'path' => 'media/adviser.webm', 'original_name' => 'adviser.webm', 'mime_type' => 'video/webm', 'size' => 1000]);
    cmsPage('meet', [['type' => 'video', 'data' => ['title' => 'Talk', 'call_style' => 'simple', 'media_type' => 'video', 'video' => $video->id]]]);

    $this->get('/meet')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.data.call_style', 'simple')
        ->where('sections.0.data.video.mime', 'video/webm'));
});

it('keeps every image and video field when a page is saved, whatever the field is called', function () {
    $image = Media::where('mime_type', 'like', 'image/%')->first();
    $video = Media::create(['disk' => 'public', 'path' => 'media/v.mp4', 'original_name' => 'v.mp4', 'mime_type' => 'video/mp4', 'size' => 1]);
    $checked = 0;

    foreach (SectionTypes::all() as $type => $def) {
        foreach ($def['fields'] as $key => $field) {
            if (! in_array($field['type'], ['image', 'video'], true)) {
                continue;
            }

            $id = $field['type'] === 'video' ? $video->id : $image->id;
            $clean = app(SectionSanitizer::class)->clean([['type' => $type, 'data' => [$key => $id]]]);

            expect($clean[0]['data'][$key])->toBe($id, "{$type}.{$key} was dropped on save");
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThanOrEqual(12);
});

it('shows the “You” tile photo in the video counseling window', function () {
    $photo = Media::where('mime_type', 'like', 'image/%')->first();
    cmsPage('meet', [['type' => 'video', 'data' => ['title' => 'Talk', 'self_image' => $photo->id]]]);

    $this->get('/meet')->assertInertia(fn (AssertableInertia $page) => $page->where('sections.0.data.self_image.url', $photo->url()));
});

it('never seeds the demo reviews outside the local environment', function () {
    expect(app()->isLocal())->toBeFalse();

    expect(fn () => $this->seed(DemoReviewsSeeder::class))->toThrow(RuntimeException::class);
    expect(Testimonial::where('role', 'like', '%(demo)')->count())->toBe(0);
});

it('builds the partner page from the reference design and requires the new questions', function () {
    $this->get('/become-a-partner')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.type', 'page_intro')
        ->where('sections.0.data.parent.label', 'Partners')
        ->where('sections.0.data.aside_title', "A shared ambition.\nA personal approach.")
        ->where('sections.1.type', 'partner_form')
        ->has('sections.1.records.interests', count(PartnerApplication::INTERESTS))
        ->has('sections.1.records.destinations', 7)
        ->where('sections.1.records.email', 'info@rtlbangladesh.com')
        ->where('sections.2.type', 'steps')
        ->has('sections.2.data.items', 3));

    $this->post(route('partners.apply'), partnerApplication(['job_title' => '', 'destinations' => [], 'partnership_interest' => 'nope', 'city' => '']))
        ->assertSessionHasErrors(['job_title', 'destinations', 'partnership_interest', 'city']);

    // Only destination countries count as study destinations.
    $this->post(route('partners.apply'), partnerApplication(['destinations' => [Country::where('iso2', 'BD')->value('id')]]))
        ->assertSessionHasErrors('destinations.0');
});

it('switches an unchanged first-version partner page to the new design', function () {
    $page = Page::where('system_key', 'partners')->first();
    $page->update(['sections' => app(SectionSanitizer::class)->clean([
        ['type' => 'page_hero', 'data' => ['eyebrow' => 'PARTNER WITH RTL', 'title' => 'Old']],
        ['type' => 'partner_form', 'data' => []],
    ])]);

    $this->seed(WebsiteContentSeeder::class);

    expect(array_column($page->fresh()->sections, 'type'))->toBe(['page_intro', 'partner_form', 'steps']);
});
