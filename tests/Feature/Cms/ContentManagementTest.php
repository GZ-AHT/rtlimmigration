<?php

use App\Enums\CommentStatus;
use App\Enums\PublishStatus;
use App\Enums\Role;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Country;
use App\Models\Course;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\Setting;
use App\Models\University;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->admin = $this->userWithRole(Role::SuperAdmin);
});

function writer(): User
{
    $role = RoleModel::findOrCreate('writer');
    $role->syncPermissions(['area.admin', 'blog.view', 'blog.manage', 'pages.view', 'pages.manage']);

    return User::factory()->create()->assignRole('writer');
}

function media(): Media
{
    return Media::create(['disk' => 'public', 'path' => 'media/x.jpg', 'original_name' => 'x.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
}

function homePage(array $sections = []): Page
{
    return Page::create(['title' => 'Home', 'slug' => 'home', 'system_key' => 'home', 'status' => PublishStatus::Published, 'visibility' => 'public', 'sections' => $sections]);
}

/* ---------------------------------------------------------------- media */

it('uploads raster images to the media library and refuses SVG', function () {
    $this->actingAs($this->admin)->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('campus.jpg', 1200, 800), 'alt' => 'Campus'])
        ->assertCreated()->assertJsonPath('width', 1200)->assertJsonPath('alt', 'Campus');

    Storage::disk('public')->assertExists(Media::sole()->path);

    $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>')])
        ->assertUnprocessable()->assertJsonValidationErrors('file');

    $this->actingAs($this->userWithRole(Role::Counselor))->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
});

/* ----------------------------------------------------------------- blog */

it('creates a post with clean HTML, tags, reading time and SEO', function () {
    $image = media();

    $this->actingAs($this->admin)->post(route('admin.blog.posts.store'), [
        'title' => 'Study in Canada',
        'content' => '<p>Hello <script>alert(1)</script><a href="javascript:alert(1)">x</a> <a href="https://rtl.test">y</a></p>'.str_repeat('<p>word word word word</p>', 120),
        'featured_image_id' => $image->id,
        'tags' => ['Canada', 'canada', 'Visas'],
        'status' => 'published',
        'seo' => ['meta_title' => 'Canada guide', 'noindex' => false],
    ])->assertRedirect();

    $post = BlogPost::sole();
    expect($post->slug)->toBe('study-in-canada')
        ->and($post->content)->not->toContain('<script')->not->toContain('javascript:')->toContain('rel="noopener noreferrer"')
        ->and($post->excerpt)->not->toBeEmpty()
        ->and($post->reading_minutes)->toBe(3)
        ->and($post->isLive())->toBeTrue()
        ->and($post->tags->pluck('name')->all())->toBe(['Canada', 'Visas'])
        ->and($post->seo->meta_title)->toBe('Canada guide');

    // A second post with the same title gets its own address.
    $this->post(route('admin.blog.posts.store'), ['title' => 'Study in Canada', 'content' => '<p>x</p>', 'status' => 'draft']);
    expect(BlogPost::latest('id')->first()->slug)->toBe('study-in-canada-2');
});

it('lets writers save drafts but only editors publish, schedule and feature', function () {
    $writer = writer();

    $this->actingAs($writer)->post(route('admin.blog.posts.store'), ['title' => 'Draft', 'content' => '<p>x</p>', 'status' => 'published', 'is_featured' => true])->assertRedirect();
    $post = BlogPost::sole();
    expect($post->status)->toBe(PublishStatus::Draft)->and($post->is_featured)->toBeFalse();

    $this->patch(route('admin.blog.posts.feature', $post))->assertForbidden();

    $this->actingAs($this->admin)->put(route('admin.blog.posts.update', $post), [
        'title' => 'Draft', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->addDay()->toDateTimeString(),
    ]);
    $post->refresh();
    expect($post->status->state($post->published_at)['key'])->toBe('scheduled')->and($post->isLive())->toBeFalse()
        ->and(BlogPost::live()->count())->toBe(0);

    $this->travel(2)->days();
    expect(BlogPost::live()->count())->toBe(1);
});

it('lists posts by state and moves them to and from the trash', function () {
    $post = BlogPost::create(['title' => 'A', 'slug' => 'a', 'content' => 'x', 'status' => PublishStatus::Published, 'published_at' => now()->subDay()]);
    BlogPost::create(['title' => 'B', 'slug' => 'b', 'content' => 'x', 'status' => PublishStatus::Draft]);

    $this->actingAs($this->admin)->get(route('admin.blog.posts.index', ['state' => 'draft']))
        ->assertInertia(fn (Assert $p) => $p->component('admin/blog/Posts')->has('posts.data', 1)->where('counts.published', 1)->where('counts.all', 2));

    $this->delete(route('admin.blog.posts.destroy', $post))->assertRedirect();
    expect(BlogPost::count())->toBe(1);
    $this->patch(route('admin.blog.posts.restore', $post->id));
    expect(BlogPost::count())->toBe(2);
});

it('moderates comments only with publish rights', function () {
    $post = BlogPost::create(['title' => 'A', 'slug' => 'a', 'content' => 'x', 'status' => PublishStatus::Published, 'published_at' => now()]);
    $comment = BlogComment::create(['blog_post_id' => $post->id, 'name' => 'Rafi', 'email' => 'r@example.com', 'body' => 'Thanks', 'status' => CommentStatus::Pending]);

    $this->actingAs(writer())->patch(route('admin.blog.comments.update', $comment), ['status' => 'approved'])->assertForbidden();
    $this->actingAs($this->admin)->patch(route('admin.blog.comments.update', $comment), ['status' => 'approved'])->assertSessionHasNoErrors();
    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);
});

it('manages categories and tags', function () {
    $this->actingAs($this->admin)->post(route('admin.blog.taxonomy.store', 'categories'), ['name' => 'Study in UK'])->assertSessionHasNoErrors();
    $this->post(route('admin.blog.taxonomy.store', 'tags'), ['name' => 'IELTS'])->assertSessionHasNoErrors();

    $this->get(route('admin.blog.taxonomy.index', 'categories'))->assertInertia(fn (Assert $p) => $p->where('items.0.slug', 'study-in-uk'));
    expect(BlogTag::sole()->slug)->toBe('ielts');

    $this->get('/admin/blog/nonsense')->assertNotFound();
});

/* ---------------------------------------------------------------- pages */

it('cleans page sections against the section types', function () {
    $image = media();

    $this->actingAs($this->admin)->post(route('admin.pages.store'), [
        'title' => 'Landing', 'status' => 'published', 'visibility' => 'public',
        'sections' => [
            ['type' => 'hero', 'data' => [
                'title' => '<b>Big</b> title', 'image' => $image->id, 'show_finder' => true,
                'primary' => ['label' => 'Go', 'url' => 'javascript:alert(1)'],
                'secondary' => ['label' => 'Services', 'url' => '/services'],
                'unknown' => 'dropped',
            ], 'settings' => ['background' => 'rainbow', 'anchor' => 'Top Area']],
            ['type' => 'rich_text', 'data' => ['content' => '<p onclick="x()">Hi<iframe src="https://evil.test"></iframe></p>']],
            ['type' => 'evil', 'data' => []],
            ['type' => 'logos', 'data' => ['items' => [['image' => 99999, 'name' => 'Ghost']]]],
        ],
    ])->assertRedirect();

    $page = Page::sole();
    [$hero, $text, $logos] = $page->sections;

    expect($page->sections)->toHaveCount(3)
        ->and($hero['data']['title'])->toBe('Big title')
        ->and($hero['data']['image'])->toBe($image->id)
        ->and($hero['data']['primary']['url'])->toBe('')
        ->and($hero['data']['secondary']['url'])->toBe('/services')
        ->and($hero['data'])->not->toHaveKey('unknown')
        ->and($hero['settings']['background'])->toBe('white')
        ->and($hero['settings']['anchor'])->toBe('top-area')
        ->and($text['data']['content'])->toBe('<p>Hi</p>')
        ->and($logos['data']['items'][0]['image'])->toBeNull();
});

it('keeps revisions and restores an earlier version', function () {
    $page = homePage([['id' => 'abcdef12', 'type' => 'rich_text', 'enabled' => true, 'data' => ['content' => '<p>One</p>'], 'settings' => []]]);

    $this->actingAs($this->admin)->put(route('admin.pages.update', $page), [
        'title' => 'Home', 'slug' => 'changed', 'status' => 'published', 'visibility' => 'public',
        'sections' => [['id' => 'abcdef12', 'type' => 'rich_text', 'data' => ['content' => '<p>Two</p>']]],
    ])->assertSessionHasNoErrors();

    $page->refresh();
    expect($page->slug)->toBe('home')
        ->and($page->sections[0]['data']['content'])->toBe('<p>Two</p>')
        ->and($page->revisions()->count())->toBe(1);

    $this->post(route('admin.pages.revisions.restore', [$page, $page->revisions()->first()]))->assertSessionHasNoErrors();
    expect($page->fresh()->sections[0]['data']['content'])->toBe('<p>One</p>')
        ->and($page->revisions()->count())->toBe(2);

    $this->delete(route('admin.pages.destroy', $page))->assertSessionHasErrors('page');
});

it('keeps reserved addresses and lets only Super Admin set custom code', function () {
    $this->actingAs(writer())->post(route('admin.pages.store'), [
        'title' => 'Promo', 'slug' => 'blog', 'status' => 'draft', 'visibility' => 'public', 'sections' => [],
    ])->assertSessionHasErrors('slug');

    $this->post(route('admin.pages.store'), [
        'title' => 'Promo', 'status' => 'published', 'visibility' => 'public', 'sections' => [], 'custom_js' => 'alert(1)',
    ])->assertRedirect();
    $page = Page::sole();
    expect($page->custom_js)->toBeNull()->and($page->status)->toBe(PublishStatus::Draft);

    $this->actingAs($this->admin)->put(route('admin.pages.update', $page), [
        'title' => 'Promo', 'status' => 'draft', 'visibility' => 'public', 'sections' => [], 'custom_css' => 'a{color:red}</style><script>x</script>', 'custom_js' => 'console.log(1)',
    ]);
    expect($page->fresh()->custom_css)->not->toContain('</style')
        ->and($page->fresh()->custom_js)->toBe('console.log(1)')
        ->and(Activity::where('log_name', 'security')->where('description', 'Page custom code changed')->exists())->toBeTrue();
});

/* ---------------------------------------------------------------- menus */

it('builds menus with safe links, one dropdown level and drag-and-drop order', function () {
    $this->actingAs($this->admin)->get(route('admin.cms.menus.index'))->assertOk();
    $menu = Menu::where('location', 'main')->sole();
    $page = homePage();

    $this->post(route('admin.cms.menus.items.store', $menu), ['label' => 'Home', 'link_type' => 'page', 'page_id' => $page->id, 'visibility' => 'everyone'])->assertSessionHasNoErrors();
    $this->post(route('admin.cms.menus.items.store', $menu), ['label' => 'Bad', 'link_type' => 'url', 'url' => 'javascript:alert(1)', 'visibility' => 'everyone'])->assertSessionHasErrors('url');
    $this->post(route('admin.cms.menus.items.store', $menu), ['label' => 'Blog', 'link_type' => 'url', 'url' => '/blog', 'visibility' => 'roles', 'roles' => ['student']])->assertSessionHasNoErrors();

    [$home, $blog] = MenuItem::orderBy('id')->get();
    expect($home->href())->toBe('/')->and($blog->roles)->toBe(['student']);

    $this->put(route('admin.cms.menus.reorder', $menu), ['tree' => [['id' => $blog->id, 'children' => [['id' => $home->id]]]]])->assertSessionHasNoErrors();
    expect($home->fresh()->parent_id)->toBe($blog->id)->and($blog->fresh()->sort_order)->toBe(10);

    // A link with children can't go inside another dropdown.
    $other = MenuItem::create(['menu_id' => $menu->id, 'label' => 'X', 'link_type' => 'url', 'url' => '/x']);
    $this->put(route('admin.cms.menus.items.update', $blog), ['label' => 'Blog', 'link_type' => 'url', 'url' => '/blog', 'parent_id' => $other->id, 'visibility' => 'everyone'])->assertSessionHasErrors('parent_id');
});

/* ------------------------------------------------------------- settings */

it('saves site settings publicly but keeps custom code private and Super Admin only', function () {
    $manager = User::factory()->create()->assignRole(RoleModel::findOrCreate('web')->syncPermissions(['area.admin', 'cms.manage']));

    $this->actingAs($manager)->put(route('admin.cms.settings.update', 'general'), ['site_name' => 'RTL Immigration', 'contact_email' => 'hello@rtl.test'])->assertSessionHasNoErrors();
    expect(app(SettingsRepository::class)->get('general.site_name'))->toBe('RTL Immigration');

    $this->put(route('admin.cms.settings.update', 'header'), ['cta' => ['label' => 'Apply', 'url' => 'javascript:x']])->assertSessionHasErrors('cta.url');
    $this->put(route('admin.cms.settings.update', 'code'), ['head_js' => '<script>x</script>'])->assertForbidden();
    $this->get(route('admin.cms.settings'))->assertInertia(fn (Assert $p) => $p->missing('groups.code'));

    $this->actingAs($this->admin)->put(route('admin.cms.settings.update', 'code'), ['head_js' => '<script>track()</script>'])->assertSessionHasNoErrors();
    expect(Setting::where('group', 'code')->where('key', 'head_js')->value('is_public'))->toBeFalse()
        ->and(app(SettingsRepository::class)->public())->not->toHaveKey('code');
});

/* --------------------------------------------------------- universities */

it('creates a university with courses and intakes', function () {
    $country = Country::create(['name' => 'Canada', 'iso2' => 'CA', 'is_destination' => true, 'is_active' => true]);

    $this->actingAs($this->admin)->post(route('admin.universities.store'), [
        'name' => 'University of Toronto', 'country_id' => $country->id, 'city' => 'Toronto', 'description' => '<p>Great<script>x</script></p>', 'is_active' => true, 'gallery' => [],
    ])->assertRedirect();
    $university = University::sole();
    expect($university->slug)->toBe('university-of-toronto')->and($university->description)->toBe('<p>Great</p>');

    $this->post(route('admin.universities.courses.store', $university), [
        'name' => 'MSc Computer Science', 'level' => 'master', 'visa_kind' => 'study', 'tuition_fee' => 32000, 'tuition_currency' => 'cad', 'intakes' => [9, 1], 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $course = Course::sole();
    expect($course->intakes)->toBe([1, 9])->and($course->intakeLabel())->toBe('Jan, Sep')->and($course->tuition_currency)->toBe('CAD');

    $other = University::create(['name' => 'Other', 'slug' => 'other', 'country_id' => $country->id]);
    $this->put(route('admin.universities.courses.update', [$other, $course]), ['name' => 'x', 'level' => 'master', 'visa_kind' => 'study'])->assertNotFound();
});

/* -------------------------------------------------------------- partners */

it('approves a partner application into a numbered partner, and requires a reason to reject', function () {
    $application = PartnerApplication::create(['organisation' => 'Dhaka Study Hub', 'contact_name' => 'Karim', 'email' => 'k@hub.test', 'type' => 'agency']);
    $other = PartnerApplication::create(['organisation' => 'Spam Co', 'contact_name' => 'X', 'email' => 'x@x.test', 'type' => 'agency']);

    $this->actingAs($this->admin)->post(route('admin.partner-applications.reject', $other), [])->assertSessionHasErrors('note');
    $this->post(route('admin.partner-applications.reject', $other), ['note' => 'Not a real agency'])->assertSessionHasNoErrors();

    $this->post(route('admin.partner-applications.approve', $application), ['commission_rate' => 10])->assertRedirect();
    $partner = Partner::sole();
    expect($partner->partner_number)->toBe('PTR-0001')->and($partner->commission_rate)->toBe('10.00')
        ->and($application->fresh())->status->toBe('approved')->partner_id->toBe($partner->id);

    $this->post(route('admin.partner-applications.approve', $application))->assertSessionHasErrors('status');

    $this->post(route('admin.partners.commissions.store', $partner), ['description' => 'Referral', 'amount' => 5000, 'currency' => 'bdt']);
    $this->post(route('admin.partners.payments.store', $partner), ['amount' => 2000, 'currency' => 'BDT', 'method' => 'Bank', 'paid_on' => now()->toDateString()]);
    $this->get(route('admin.partners.show', $partner))->assertInertia(fn (Assert $p) => $p->where('totals.owed', '5,000.00')->where('totals.paid', '2,000.00'));
});

/* ------------------------------------------------------------ access */

it('shows the website modules in the admin menu and keeps them from other roles', function () {
    $this->actingAs($this->admin)->get(route('admin.blog.posts.index'))->assertOk();

    $counselor = $this->userWithRole(Role::Counselor);
    $this->actingAs($counselor)->get(route('admin.blog.posts.index'))->assertForbidden();

    $manager = $this->userWithRole(Role::LeadManager);
    foreach (['admin.blog.posts.index', 'admin.pages.index', 'admin.cms.settings', 'admin.universities.index', 'admin.partners.index', 'admin.media.index'] as $route) {
        $this->actingAs($manager)->get(route($route))->assertForbidden();
    }
});

it('opens every website admin screen', function () {
    $country = Country::create(['name' => 'Canada', 'iso2' => 'CA', 'is_destination' => true, 'is_active' => true]);
    $university = University::create(['name' => 'Toronto', 'slug' => 'toronto', 'country_id' => $country->id]);
    $post = BlogPost::create(['title' => 'A', 'slug' => 'a', 'content' => '<p>x</p>', 'status' => PublishStatus::Draft]);
    $page = homePage([['id' => 'abcdef12', 'type' => 'hero', 'enabled' => true, 'data' => ['image' => media()->id], 'settings' => []]]);
    $application = PartnerApplication::create(['organisation' => 'Hub', 'contact_name' => 'K', 'email' => 'k@hub.test', 'type' => 'agency']);
    $partner = Partner::create(['partner_number' => 'PTR-0009', 'organisation' => 'Hub', 'contact_name' => 'K', 'email' => 'k@hub.test']);

    $screens = [
        [route('admin.media.index'), 'admin/media/Index'],
        [route('admin.blog.posts.index'), 'admin/blog/Posts'],
        [route('admin.blog.posts.create'), 'admin/blog/PostForm'],
        [route('admin.blog.posts.edit', $post), 'admin/blog/PostForm'],
        [route('admin.blog.taxonomy.index', 'tags'), 'admin/blog/Taxonomy'],
        [route('admin.blog.comments.index'), 'admin/blog/Comments'],
        [route('admin.blog.authors.index'), 'admin/blog/Authors'],
        [route('admin.pages.index'), 'admin/cms/Pages'],
        [route('admin.pages.create'), 'admin/cms/PageEditor'],
        [route('admin.pages.edit', $page), 'admin/cms/PageEditor'],
        [route('admin.cms.settings'), 'admin/cms/SiteSettings'],
        [route('admin.cms.menus.index', ['location' => 'footer']), 'admin/cms/Menus'],
        [route('admin.cms.faqs.index'), 'admin/cms/Faqs'],
        [route('admin.cms.testimonials.index'), 'admin/cms/Testimonials'],
        [route('admin.cms.newsletter.index'), 'admin/cms/Newsletter'],
        [route('admin.universities.index'), 'admin/universities/Index'],
        [route('admin.universities.create'), 'admin/universities/Form'],
        [route('admin.universities.edit', $university), 'admin/universities/Form'],
        [route('admin.countries.index'), 'admin/universities/Countries'],
        [route('admin.partner-applications.index'), 'admin/partners/Applications'],
        [route('admin.partner-applications.show', $application), 'admin/partners/ApplicationShow'],
        [route('admin.partners.index'), 'admin/partners/Index'],
        [route('admin.partners.show', $partner), 'admin/partners/Show'],
    ];

    foreach ($screens as [$url, $component]) {
        $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($component));
    }

    $this->get(route('admin.cms.homepage'))->assertRedirect(route('admin.pages.edit', $page));
    $this->get(route('admin.pages.edit', $page))->assertInertia(fn (Assert $p) => $p->has('media', 1)->has('sectionTypes.hero'));
});
