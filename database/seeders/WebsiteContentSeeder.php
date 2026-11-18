<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Testimonial;
use App\Support\Cms\SectionSanitizer;
use App\Support\Cms\SectionTypes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The website's built-in pages, menus and starter content (Phase 9b),
 * taken from the Figma file RTLIMMIG. Pages the Figma file doesn't have
 * (services, legal, reviews, universities) are built from the same
 * sections with placeholder copy for the owner to edit.
 *
 * Safe to run again: anything that already exists (by slug, system key,
 * menu location or question) is left as the admins edited it.
 */
class WebsiteContentSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $media = [];

    public function run(): void
    {
        $this->importImages();
        $this->seedContent();
        $this->seedPages();
        $this->seedMenus();
    }

    /** Copies the Figma photos from public/images/site into the media library once. */
    private function importImages(): void
    {
        $alts = [
            'hero-campus.png' => 'Student outside a modern campus building',
            'pathway-diploma.jpg' => 'Hands-on electrical training',
            'pathway-bachelors.jpg' => 'Two students working together on a laptop',
            'pathway-masters.jpg' => 'Graduate receiving congratulations at a ceremony',
            'about-team.jpg' => 'RTL adviser speaking with a student',
            'video-adviser.jpg' => 'RTL adviser on a video call',
            'blog-armenia.jpg' => 'Study in Armenia',
            'blog-denmark.jpg' => 'Study in Denmark: Bachelor and Master’s requirements',
            'blog-new-zealand.jpg' => 'New Zealand student visa for Bangladeshi students',
            'about-hero.jpg' => 'Students studying, advising and graduating abroad',
            'about-leadership.jpg' => 'RTL leadership',
            'success-canada-client.jpg' => 'Canada client success',
            'success-canada-visit.jpg' => 'Canada visit visa success',
            'success-canada-work.jpg' => 'Canada work visa success',
            'success-canada-student.jpg' => 'Canada student visa success',
        ];

        foreach ($alts as $file => $alt) {
            $source = public_path('images/site/'.$file);
            $name = 'figma-'.$file;
            $existing = Media::query()->where('original_name', $name)->first();

            if ($existing) {
                $this->media[$file] = $existing->id;

                continue;
            }

            if (! File::exists($source)) {
                continue;
            }

            $path = 'media/site/'.Str::random(32).'.'.pathinfo($file, PATHINFO_EXTENSION);
            Storage::disk('public')->put($path, File::get($source));
            $size = @getimagesize($source) ?: [null, null];

            $this->media[$file] = Media::create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => $name,
                'mime_type' => (string) File::mimeType($source),
                'size' => File::size($source),
                'width' => $size[0],
                'height' => $size[1],
                'alt' => $alt,
            ])->id;
        }
    }

    private function img(string $file): ?int
    {
        return $this->media[$file] ?? null;
    }

    /** FAQs, a testimonial and the three articles from the Figma homepage. */
    private function seedContent(): void
    {
        $faqs = [
            'Student visa' => [
                ['How can RTL help me get a student visa?', 'We assist with university selection, documentation, visa applications and interview preparation, helping you navigate each stage with confidence.', true],
                ['Which countries can I apply to for studying abroad?', 'We guide applications for destinations including the USA, Canada, Australia, the UK, Germany, Malaysia, Japan, New Zealand, Denmark and Armenia. Talk to an adviser about which suits your goals and budget.', true],
                ['What are the basic requirements for a student visa?', 'Requirements depend on the country and course, but usually include an offer letter from an institution, proof of funds, academic documents, a valid passport and, for many destinations, an English language test result. We give you a checklist for your exact application.', true],
                ['Can RTL guarantee visa approval?', 'No. Visa decisions are made by the embassy or immigration authority, so nobody can honestly guarantee approval. What we do is prepare a complete, accurate application and help you present it with confidence.', true],
                ['How long does a student visa application take?', 'It varies by country and season. Plan for several weeks to a few months from offer letter to decision. Starting early leaves time for documents, tests and any interview.', true],
            ],
            'Visitor visa' => [
                ['Can you help with a visitor or tourist visa?', 'Yes. We help you prepare the application, supporting documents and travel plans for family visits, tourism and short business trips.', true],
            ],
            'Skilled migration' => [
                ['Do you advise on skilled migration?', 'Yes. We look at your qualifications, experience and goals and explain the pathways that may fit you, before you commit to an application.', true],
            ],
        ];

        $sort = 0;
        foreach ($faqs as $category => $items) {
            $cat = FaqCategory::firstOrCreate(['slug' => Str::slug($category)], ['name' => $category, 'sort_order' => $sort += 10]);

            foreach ($items as $i => [$question, $answer, $home]) {
                Faq::firstOrCreate(['question' => $question], ['faq_category_id' => $cat->id, 'answer' => $answer, 'show_on_home' => $home, 'is_active' => true, 'sort_order' => ($i + 1) * 10]);
            }
        }

        Testimonial::firstOrCreate(['name' => 'Jubaida Hasan'], [
            'role' => 'Student review · RTL Bangladesh',
            'quote' => 'RTL is best educational consultant because of their support and cooperative behaviour. They helped and support me in every step of my application and visa processing.',
            'rating' => 5, 'is_active' => true, 'is_featured' => true, 'sort_order' => 10,
        ]);

        // Figma "RTL Bangladesh — Blog": the category pills, and each card's label (first tag).
        $categories = [];
        foreach (['Study destinations', 'Visa guidance', 'Student life', 'Planning your journey'] as $i => $name) {
            $categories[$name] = BlogCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true, 'sort_order' => ($i + 1) * 10])->id;
        }

        // [title, excerpt, image, date, category, tag, featured]
        $posts = [
            ['Your next chapter starts with a plan.', 'From choosing a course to preparing your documents, make the big decisions feel a little more manageable.', 'hero-campus.png', '2026-11-10', 'Planning your journey', 'Study abroad', true],
            ['Study in Armenia from Bangladesh', 'A guide to fees, visas and requirements for your study abroad plans.', 'blog-armenia.jpg', '2026-11-02', 'Study destinations', 'Armenia', false],
            ['Your guide to studying in Denmark', 'Explore Bachelor’s and Master’s study options for Bangladeshi students.', 'blog-denmark.jpg', '2026-09-28', 'Study destinations', 'Denmark', false],
            ['Planning to study in New Zealand?', 'Start with RTL’s student visa guide for Bangladeshi students.', 'blog-new-zealand.jpg', '2026-02-28', 'Study destinations', 'New Zealand', false],
            ['Find a course that fits your future', 'Questions to ask when comparing your study options abroad.', 'pathway-diploma.jpg', '2026-02-12', 'Planning your journey', null, false],
            ['Before you press submit', 'A thoughtful approach to organising your academic application documents.', 'pathway-bachelors.jpg', '2026-01-20', 'Visa guidance', 'Application guidance', false],
            ['A confident start to campus life', 'Make space for new routines, new connections and a new place to call home.', 'pathway-masters.jpg', '2026-01-05', 'Student life', null, false],
        ];

        foreach ($posts as [$title, $excerpt, $image, $date, $category, $tag, $featured]) {
            $post = BlogPost::firstOrCreate(['slug' => Str::slug($title)], [
                'title' => $title,
                'excerpt' => $excerpt,
                'content' => "<p>{$excerpt}</p><h2>Start with your goals</h2><p>Every student’s situation is different. Before you apply, think about the courses you want, your budget, how long you plan to stay and what you hope to do after graduating.</p><h2>What you’ll usually need</h2><ul><li>An offer from a recognised institution</li><li>Academic certificates and transcripts</li><li>Proof that you can pay your fees and living costs</li><li>A valid passport, and an English test result where required</li></ul><p>Requirements change, so always check the latest rules for your course and intake. An RTL adviser can give you a checklist for your exact application.</p>",
                'featured_image_id' => $this->img($image),
                'blog_category_id' => $categories[$category],
                'status' => PublishStatus::Published,
                'published_at' => $date.' 09:00:00',
                'is_featured' => $featured,
                'allow_comments' => true,
                'reading_minutes' => 3,
            ]);

            if ($tag && $post->wasRecentlyCreated) {
                $post->tags()->sync([BlogTag::firstOrCreate(['slug' => Str::slug($tag)], ['name' => $tag])->id]);
            }
        }
    }

    private function seedPages(): void
    {
        $pages = [
            ['home', 'home', 'Home', $this->home()],
            ['about', 'about-us', 'About us', $this->about()],
            ['contact', 'contact', 'Contact us', $this->contact()],
            ['faq', 'faq', 'Frequently asked questions', $this->faqPage()],
            ['blog', 'blog', 'Blog & insights', $this->blogPage()],
            ['universities', 'universities', 'Our partner universities', $this->universitiesPage()],
            ['reviews', 'reviews', 'Student reviews', $this->reviewsPage()],
            ['partners', 'become-a-partner', 'Partner application', $this->partnerPage()],
            ['privacy', 'privacy-policy', 'Privacy policy', $this->legal('Privacy policy', 'How we collect, use and protect your personal information.')],
            ['terms', 'terms-and-conditions', 'Terms & conditions', $this->legal('Terms & conditions', 'The terms that apply when you use this website and our services.')],
            ['cookies', 'cookie-policy', 'Cookie policy', $this->legal('Cookie policy', 'How this website uses cookies and how you can control them.')],
        ];

        foreach ($this->services() as $slug => [$title, $sections]) {
            $pages[] = [null, $slug, $title, $sections, 'services'];
        }

        $sanitizer = app(SectionSanitizer::class);

        // Homepages made before the skill migration search get it, after the statistics.
        $home = Page::query()->where('system_key', 'home')->first();
        if ($home && ! in_array('skill_search', array_column($home->sections, 'type'), true)) {
            $sections = $home->sections;
            $at = array_search('stats', array_column($sections, 'type'), true);
            array_splice($sections, $at === false ? count($sections) : $at + 1, 0, [$this->skillSearch()]);
            $home->update(['sections' => $sanitizer->clean($sections)]);
        }

        // Homepages made before the second banner design get it, hidden, after the first.
        $home = Page::query()->where('system_key', 'home')->first();
        if ($home && ! in_array('hero_cover', array_column($home->sections, 'type'), true)) {
            $sections = $home->sections;
            $at = array_search('hero', array_column($sections, 'type'), true);
            array_splice($sections, $at === false ? 0 : $at + 1, 0, [$this->heroCover()]);
            $home->update(['sections' => $sanitizer->clean($sections)]);
        }

        // The first 9b partner page (plain header + form) moves to the new design if unchanged.
        $partners = Page::query()->where('system_key', 'partners')->first();
        if ($partners && array_column($partners->sections, 'type') === ['page_hero', 'partner_form'] && ($partners->sections[0]['data']['eyebrow'] ?? '') === 'PARTNER WITH RTL') {
            $partners->update(['sections' => $sanitizer->clean($this->partnerPage())]);
        }

        // The first 9b Blog page used a plain header; switch it to the Figma layout if it is unchanged.
        $blog = Page::query()->where('system_key', 'blog')->first();
        if ($blog && array_column($blog->sections, 'type') === ['page_hero', 'blog', 'newsletter'] && ($blog->sections[0]['data']['eyebrow'] ?? '') === 'INSIGHTS FOR YOUR NEXT STEP') {
            $blog->update(['sections' => $sanitizer->clean($this->blogPage())]);
        }

        // The first 9b Contact page had only the form; add the Figma header and
        // visit sections if an admin hasn't changed it since.
        $contact = Page::query()->where('system_key', 'contact')->first();
        if ($contact && count($contact->sections) === 1 && $contact->sections[0]['type'] === 'contact') {
            $contact->update(['sections' => $sanitizer->clean([
                $this->contactHero(),
                [...$contact->sections[0], 'settings' => [...$contact->sections[0]['settings'], 'spacing' => 'joined']],
                $this->contactVisit(),
            ])]);
        }

        foreach ($pages as $row) {
            [$key, $slug, $title, $sections] = $row;
            $exists = $key ? Page::withTrashed()->where('system_key', $key)->exists() : Page::withTrashed()->where('slug', $slug)->exists();

            if ($exists) {
                continue;
            }

            Page::create([
                'title' => $title,
                'slug' => $slug,
                'system_key' => $key,
                'category' => $row[4] ?? ($key ? 'built-in' : null),
                'status' => PublishStatus::Published,
                'published_at' => now(),
                'visibility' => 'public',
                'sections' => $sanitizer->clean($sections),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function s(string $type, array $data, array $settings = []): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'enabled' => true,
            'data' => array_replace(SectionTypes::defaults($type), $data),
            'settings' => array_replace(['background' => 'white', 'spacing' => 'default', 'anchor' => '', 'hide_on_mobile' => false], $settings),
        ];
    }

    /** @return array{label: string, url: string} */
    private function link(string $label, string $url): array
    {
        return ['label' => $label, 'url' => $url];
    }

    /** @return list<array<string, mixed>> */
    private function home(): array
    {
        return [
            $this->s('hero', [
                'eyebrow' => 'YOUR AMBITION. A WORLD OF POSSIBILITIES.',
                'title' => 'Your future.',
                'title_accent' => "Without\nborders.",
                'description' => 'From choosing your course to preparing your visa application, your next chapter starts with expert guidance.',
                'primary' => $this->link('Book a consultation', '/contact'),
                'secondary' => $this->link('Explore our services', '/student-visa'),
                'image' => $this->img('hero-campus.png'),
                'image_caption' => 'STUDY ABROAD, WITH RTL',
                'show_finder' => true,
                'finder_tabs' => [
                    ['label' => 'Student visa', 'url' => ''],
                    ['label' => 'Schooling visa', 'url' => '/schooling-visa'],
                    ['label' => 'Visitor visa', 'url' => '/visitor-visa'],
                    ['label' => 'Skilled migration', 'url' => '/skilled-migration'],
                ],
                'finder_help' => $this->link('Need help?', '/contact'),
            ], ['background' => 'sand']),
            $this->heroCover(),
            $this->s('pathways', [
                'eyebrow' => 'FIND YOUR DIRECTION',
                'title' => 'Big ambitions. The right pathway.',
                'description' => 'Explore study options that fit your goals, wherever you want to go.',
                'link' => $this->link('Talk to an adviser', '/contact'),
                'items' => [
                    ['image' => $this->img('pathway-diploma.jpg'), 'eyebrow' => 'BUILD YOUR FOUNDATION', 'title' => 'Diploma', 'text' => "Practical skills. A strong foundation.\nTake your first step into global education.", 'link' => $this->link('Diploma', '/universities?level=diploma')],
                    ['image' => $this->img('pathway-bachelors.jpg'), 'eyebrow' => 'SHAPE YOUR FUTURE', 'title' => 'Bachelor’s', 'text' => "Find a degree that turns your interests\ninto your next big opportunity.", 'link' => $this->link('Bachelor’s', '/universities?level=bachelor')],
                    ['image' => $this->img('pathway-masters.jpg'), 'eyebrow' => 'TAKE THE NEXT STEP', 'title' => 'Master’s', 'text' => "Deepen your expertise and take your\nacademic ambitions further.", 'link' => $this->link('Master’s', '/universities?level=master')],
                ],
            ]),
            $this->s('stats', [
                'items' => [
                    ['value' => '5,000+', 'label' => 'Successful student visas'],
                    ['value' => '30+', 'label' => 'Connected countries'],
                    ['value' => '23+', 'label' => 'Years of experience'],
                    ['value' => '1,200+', 'label' => 'Positive reviews'],
                ],
                'note' => 'Figures published by RTL Bangladesh.',
                'link' => $this->link('Meet our university partners', '/universities'),
            ], ['background' => 'sand', 'spacing' => 'compact']),
            $this->skillSearch(),
            $this->s('about', [
                'image' => $this->img('about-team.jpg'),
                'caption_eyebrow' => 'REAL PEOPLE. PERSONAL GUIDANCE.',
                'caption' => 'A clearer way to begin your journey abroad.',
                'eyebrow' => 'ABOUT RTL BANGLADESH',
                'title' => "A big decision.\nA team by your side.",
                'description' => 'RTL Immigration and Visa Advisory helps students and families navigate international education with clear, personalised support—from the first conversation to the next step abroad.',
                'steps' => [
                    ['title' => 'Find your fit', 'text' => 'Course, institution and destination guidance.'],
                    ['title' => 'Build your application', 'text' => 'Admission support and document preparation.'],
                    ['title' => 'Prepare with confidence', 'text' => 'Visa application and interview guidance.'],
                ],
                'link' => $this->link('Get to know RTL', '/about-us'),
            ], ['background' => 'navy']),
            $this->s('testimonials', [
                'eyebrow' => 'STUDENT STORIES',
                'title' => "Real journeys.\nLasting confidence.",
                'description' => "Hear from students who took their\nnext step with RTL by their side.",
                'link' => $this->link('Read student reviews', '/reviews'),
                'limit' => 3,
            ]),
            $this->s('video', [
                'eyebrow' => 'PERSONAL GUIDANCE. WHEREVER YOU ARE.',
                'title' => "Big plans.\nLet’s talk,\nface to face.",
                'description' => 'Connect with an RTL adviser over video. Explore your study options, ask your visa questions, and plan your next step—from wherever you call home.',
                'button' => 'Book a video consultation',
                'detail' => 'One-to-one guidance · Online, on your schedule',
                'image' => $this->img('video-adviser.jpg'),
                'adviser_name' => 'Your RTL adviser',
                'room_label' => 'RTL  /  VIDEO COUNSELING',
                'reassurance' => 'A real conversation. A clearer direction.',
                'steps' => [
                    ['title' => 'Choose your time', 'text' => 'Find a slot that works for you.'],
                    ['title' => 'Share your goals', 'text' => 'Tell us where you want to go.'],
                    ['title' => 'Meet your adviser', 'text' => 'Leave with a clearer next step.'],
                ],
            ], ['background' => 'sand']),
            $this->s('blog', [
                'eyebrow' => 'INSIGHTS FOR YOUR NEXT STEP',
                'title' => 'A little knowledge. A world of possibility.',
                'description' => 'Practical guides and updates for students and families planning a future abroad.',
                'link' => $this->link('View all articles', '/blog'),
                'limit' => 3,
                'category' => 'study-destinations',
            ]),
            $this->s('faq', [
                'eyebrow' => 'HERE TO HELP',
                'title' => "Big questions.\nClear answers.",
                'description' => 'A little clarity before your next step.',
            ], ['background' => 'sand']),
        ];
    }

    /**
     * Homepage skill migration search (country-based assessments).
     *
     * @return array<string, mixed>
     */
    private function skillSearch(): array
    {
        return $this->s('skill_search', [
            'eyebrow' => 'SKILL MIGRATION',
            'title' => "Where can your skills\ntake you?",
            'description' => 'Choose a destination and take a free, quick assessment to see how your skills, experience and English compare.',
            'placeholder' => 'Search your skill migration destination...',
            'button' => 'Find Skill Migration Assessment',
            'link' => $this->link('See all destinations', '/skill-migration'),
        ], ['background' => 'navy']);
    }

    /**
     * Banner Design 2 (owner's reference "Hero and service finder.png"),
     * hidden until chosen in Website CMS → Homepage → Banner design.
     *
     * @return array<string, mixed>
     */
    private function heroCover(): array
    {
        $section = $this->s('hero_cover', [
            'eyebrow' => 'YOUR AMBITION. A WORLD OF POSSIBILITIES.',
            'title' => 'Your future.',
            'title_accent' => 'Without borders.',
            'description' => "From choosing your course to preparing your visa application,\nyour next chapter starts with expert guidance.",
            'primary' => $this->link('Book a consultation', '/contact'),
            'secondary' => $this->link('Explore our services', '/student-visa'),
            'media_type' => 'image',
            'image' => $this->img('hero-campus.png'),
            'image_caption' => 'STUDY ABROAD, WITH RTL',
            'show_finder' => true,
            'finder_tabs' => [
                ['label' => 'Student visa', 'url' => ''],
                ['label' => 'Schooling visa', 'url' => '/schooling-visa'],
                ['label' => 'Visitor visa', 'url' => '/visitor-visa'],
                ['label' => 'Skilled migration', 'url' => '/skilled-migration'],
            ],
            'finder_help' => $this->link('Need help?', '/contact'),
        ], ['spacing' => 'none']);
        $section['enabled'] = false;

        return $section;
    }

    /** @return list<array<string, mixed>> */
    private function about(): array
    {
        return [
            $this->s('page_hero', [
                'eyebrow' => 'PEOPLE FIRST. POSSIBILITIES WITHOUT BORDERS.',
                'title' => "Your ambition.\nOur commitment.",
                'description' => 'Personal guidance. Global perspective. We help students and families make confident decisions about their future abroad.',
                'button' => $this->link('Get to know RTL', '#our-story'),
                'footnote' => 'A CONCERN OF RTBL GROUP',
                'image' => $this->img('about-hero.jpg'),
            ], ['background' => 'blush', 'spacing' => 'none']),
            $this->s('story', [
                'eyebrow' => 'OUR STORY',
                'title' => "Rooted in Bangladesh.\nConnected to the world.",
                'description' => 'RTL Immigration and Visa Advisory simplifies visa and study-abroad journeys with clear, personalised support. A concern of the RTBL Group, we combine immigration and education consulting to help you take your next step with confidence.',
                'text' => 'From our home in Gulshan 1, Dhaka, our network extends to Melbourne, Hong Kong and the UAE. We guide applications for the USA, Canada, Australia, Germany, the UK, Malaysia, Japan and New Zealand.',
                'list_title' => 'Your journey, thoughtfully supported.',
                'items' => [
                    ['title' => 'Study & student visas', 'text' => 'Course choices, admissions and student visa guidance.'],
                    ['title' => 'Skilled migration', 'text' => 'Personalised pathways aligned with your goals.'],
                    ['title' => 'Family & visitor visas', 'text' => 'Clear advice for the people and places that matter.'],
                    ['title' => 'Work permits', 'text' => 'Support for your next professional chapter.'],
                ],
            ], ['anchor' => 'our-story']),
            $this->s('mission', [
                'eyebrow' => 'WHAT DRIVES US',
                'title' => 'A clear purpose. A shared direction.',
                'cards' => [
                    ['eyebrow' => '01   /   OUR MISSION', 'title' => "Make global\nmobility simpler.", 'text' => 'We simplify visa and permit processes to create seamless journeys for travelers, while supporting more efficient decision-making for institutions and governments.', 'text2' => 'Through secure, data-driven solutions and trusted partnerships, we aim to make travel more accessible, reduce barriers, and connect people with opportunities worldwide.'],
                    ['eyebrow' => '02   /   OUR VISION', 'title' => "Opportunity,\nwithout uncertainty.", 'text' => 'We envision a world where visa applications feel clear, not confusing. With expert guidance, seamless processes and confidence at every step, your focus stays on the journey ahead.', 'text2' => 'More than processing applications, we open doors to new possibilities with precision, clarity and trust—making global ambitions feel within reach.'],
                ],
                'values' => [['label' => 'CLARITY'], ['label' => 'INTEGRITY'], ['label' => 'PERSONALISED CARE'], ['label' => 'GLOBAL PERSPECTIVE']],
            ], ['background' => 'navy']),
            $this->s('features', [
                'eyebrow' => 'COMPANY CREDENTIALS',
                'title' => 'The foundations of your trust.',
                'description' => 'Professional expertise, transparent advice and a commitment to doing things right.',
                'items' => [
                    ['title' => 'Company registration', 'text' => 'Registered with the Australian Business Registry (ABR).'],
                    ['title' => 'Professional expertise', 'text' => 'MARA / CICC-licensed consultants, as stated in RTL’s company profile.'],
                    ['title' => 'Transparent guidance', 'text' => 'Personalised visa strategies, clear fees and no false promises.'],
                ],
            ]),
            $this->s('gallery', [
                'eyebrow' => 'STORIES BEHIND THE JOURNEY',
                'title' => 'Success comes from passion.',
                'description' => 'A selection of RTL’s visa-success stories, retained from the company archive.',
                'items' => [
                    ['image' => $this->img('success-canada-client.jpg'), 'caption' => 'Canada · Client success'],
                    ['image' => $this->img('success-canada-visit.jpg'), 'caption' => 'Canada · Visit visa'],
                    ['image' => $this->img('success-canada-work.jpg'), 'caption' => 'Canada · Work visa'],
                    ['image' => $this->img('success-canada-student.jpg'), 'caption' => 'Canada · Student visa'],
                ],
            ], ['background' => 'blush']),
            $this->s('message', [
                'image' => $this->img('about-leadership.jpg'),
                'eyebrow' => 'MESSAGE FROM RTL',
                'title' => "Your goals deserve\na committed partner.",
                'description' => 'At RTL Immigration and Visa Advisory, we help people move forward with confidence. From skilled migration and student visas to family sponsorships and work permits, we bring a personalised approach to every application.',
                'text' => 'Our team supports you from document preparation to interview coaching, with detailed case assessments, transparent advice and care for your individual circumstances.',
                'highlight' => '23+ years of trusted expertise',
                'detail' => 'Personalised strategies  /  Transparent fees  /  End-to-end support',
                'button' => $this->link('Talk to our team', '/contact'),
            ]),
        ];
    }

    /** @return list<array<string, mixed>> Figma "RTL Bangladesh — Contact". */
    private function contact(): array
    {
        return [$this->contactHero(), $this->contactForm(), $this->contactVisit()];
    }

    /** @return array<string, mixed> */
    private function contactHero(): array
    {
        return $this->s('contact_hero', [
            'eyebrow' => 'LET’S START A CONVERSATION',
            'title' => 'Big plans?',
            'title_accent' => 'Let’s talk.',
            'description' => "Your questions. Your ambitions. A real person to help.\nConnect with RTL and take your next step with confidence.",
            'tags' => 'STUDY ABROAD  /  VISA GUIDANCE  /  PARTNERSHIPS',
            'image' => $this->img('about-team.jpg'),
            'image_caption' => $this->link('Real people. Personal guidance.', '/about-us'),
            'cards' => [
                ['kind' => 'phone', 'label' => 'CALL US', 'title' => '', 'text' => 'Talk through your next step.', 'link_label' => 'Call our team', 'url' => ''],
                ['kind' => 'email', 'label' => 'EMAIL US', 'title' => '', 'text' => 'A little detail helps us guide you better.', 'link_label' => 'Send an email', 'url' => ''],
                ['kind' => 'whatsapp', 'label' => 'WHATSAPP', 'title' => 'A conversation, your way.', 'text' => 'Message our team when it suits you.', 'link_label' => 'Chat on WhatsApp', 'url' => ''],
            ],
        ], ['background' => 'surface']);
    }

    /** @return array<string, mixed> */
    private function contactForm(): array
    {
        return $this->s('contact', [
            'eyebrow' => 'A LITTLE ABOUT YOU',
            'title' => 'Where would you like to go?',
            'description' => 'Tell us what’s on your mind. We’ll help you find a clear way forward.',
            'aside_eyebrow' => 'A CLEARER NEXT STEP',
            'aside_title' => "You bring the ambition.\nWe bring the guidance.",
            'steps' => [
                ['title' => 'We listen first', 'text' => 'We’ll understand your background, your goals and what matters to you.'],
                ['title' => 'We explore your options', 'text' => 'Discuss courses, destinations and practical application next steps.'],
                ['title' => 'We plan your way forward', 'text' => 'Leave with a clearer idea of what to prepare and where to begin.'],
            ],
            'note_title' => 'Not in Dhaka? No problem.',
            'note_text' => 'Ask about a video consultation and connect with us from wherever you are.',
            'show_details' => false,
        ], ['background' => 'surface', 'spacing' => 'joined']);
    }

    /** @return array<string, mixed> */
    private function contactVisit(): array
    {
        return $this->s('visit', [
            'eyebrow' => 'MEET US IN DHAKA',
            'title' => "A face-to-face start.\nA world of possibilities.",
            'address' => '',
            'note' => 'Please contact our team to arrange your visit.',
            // Gulshan 1, Dhaka (approximate; set the exact office location in the CMS).
            'latitude' => '23.7806',
            'longitude' => '90.4167',
            'map_link' => $this->link('Open in Google Maps', ''),
            'tips_title' => 'MAKE THE MOST OF YOUR CONVERSATION',
            'tips' => [['text' => 'Your latest academic qualifications'], ['text' => 'Your preferred course and destination'], ['text' => 'Your questions, timeline and budget']],
            'tips_note' => 'Already working with RTL? Have your reference details ready.',
        ], ['background' => 'surface', 'spacing' => 'joined']);
    }

    /** @return list<array<string, mixed>> */
    private function faqPage(): array
    {
        return [
            $this->s('page_hero', ['eyebrow' => 'HERE TO HELP', 'title' => "Big questions.\nClear answers.", 'description' => 'Answers to the questions students and families ask us most. Can’t find yours? Our team is a message away.', 'button' => $this->link('Ask us a question', '/contact')], ['background' => 'blush', 'spacing' => 'none']),
            $this->s('faq', ['eyebrow' => 'BROWSE BY TOPIC', 'title' => 'Everything in one place.', 'all' => true], ['background' => 'sand']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function blogPage(): array
    {
        return [
            $this->s('page_intro', [
                'eyebrow' => 'THE RTL JOURNAL',
                'title' => "A little knowledge.\nA world of possibility.",
                'description' => 'Practical guides, fresh perspectives and a clearer path to your future abroad.',
                'aside' => $this->link('IDEAS FOR YOUR NEXT CHAPTER.', '/contact'),
            ], ['background' => 'blush']),
            $this->s('blog', [
                'title' => 'Explore the journal',
                'all' => true,
                'featured_label' => 'EDITOR’S PICK',
                'featured_note' => 'A practical starting point for students and families.',
                'featured_button' => 'Read the story',
            ]),
            $this->s('newsletter', [
                'eyebrow' => 'STAY CURIOUS. STAY INFORMED.',
                'title' => "A little guidance,\nstraight to your inbox.",
                'description' => 'Study inspiration and practical guidance for your next chapter.',
                'button' => 'Subscribe',
                'note' => "By subscribing, you agree to our Privacy Policy.\nYou can unsubscribe at any time.",
            ], ['background' => 'blush']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function universitiesPage(): array
    {
        return [
            $this->s('page_hero', ['eyebrow' => 'FIND YOUR PATHWAY', 'title' => "The right course.\nThe right place.", 'description' => 'Search our partner universities by destination and study level, then talk to an adviser about your options.', 'button' => $this->link('Talk to an adviser', '/contact')], ['background' => 'blush', 'spacing' => 'none']),
            $this->s('universities', ['eyebrow' => 'PARTNER UNIVERSITIES', 'title' => 'Explore your options.', 'all' => true]),
            $this->s('countries', ['eyebrow' => 'POPULAR DESTINATIONS', 'title' => 'Where would you like to study?', 'limit' => 8], ['background' => 'sand']),
            $this->s('cta', ['title' => 'Are you an agent or institution?', 'description' => 'Partner with RTL to support more students on their journey abroad.', 'button' => $this->link('Become a partner', '/become-a-partner')]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function reviewsPage(): array
    {
        return [
            $this->s('page_hero', ['eyebrow' => 'STUDENT STORIES', 'title' => "Real journeys.\nLasting confidence.", 'description' => 'Hear from students who took their next step with RTL by their side.', 'button' => $this->link('Start your journey', '/contact')], ['background' => 'blush', 'spacing' => 'none']),
            $this->s('testimonials', ['eyebrow' => 'IN THEIR WORDS', 'title' => 'What our students say.', 'all' => true]),
            $this->s('gallery', [
                'eyebrow' => 'STORIES BEHIND THE JOURNEY',
                'title' => 'Success comes from passion.',
                'items' => [
                    ['image' => $this->img('success-canada-client.jpg'), 'caption' => 'Canada · Client success'],
                    ['image' => $this->img('success-canada-visit.jpg'), 'caption' => 'Canada · Visit visa'],
                    ['image' => $this->img('success-canada-work.jpg'), 'caption' => 'Canada · Work visa'],
                    ['image' => $this->img('success-canada-student.jpg'), 'caption' => 'Canada · Student visa'],
                ],
            ], ['background' => 'blush']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function partnerPage(): array
    {
        // Owner's reference "RTL Bangladesh — Partner application.png".
        return [
            $this->s('page_intro', [
                'eyebrow' => 'PARTNER WITH RTL BANGLADESH',
                'title' => "Stronger partnerships.\nBrighter futures.",
                'description' => "Join our network of education and referral partners. Together, let’s help\nmore students take their next step abroad.",
                'parent' => $this->link('Partners', '/universities'),
                'aside_eyebrow' => 'LET’S GROW TOGETHER',
                'aside_title' => "A shared ambition.\nA personal approach.",
                'aside_text' => "For education agencies, institutions\nand professional referral partners.",
            ], ['background' => 'blush']),
            $this->s('partner_form', [
                'title' => 'Become an RTL partner',
                'description' => 'Tell us about your organisation and the people behind it.',
                'aside_eyebrow' => 'BETTER, TOGETHER',
                'aside_title' => "A partnership built\naround people.",
                'benefits' => [
                    ['title' => 'Work with an experienced team', 'text' => 'Connect with advisers who understand international education and visa pathways.'],
                    ['title' => 'Support every next step', 'text' => 'Coordinate course guidance, admissions and application preparation.'],
                    ['title' => 'Grow through collaboration', 'text' => 'Explore opportunities that fit your organisation and the students you serve.'],
                ],
                'question_title' => 'Have a question first?',
                'question_text' => "Our team can help you understand\nthe partnership application process.",
                'tips_title' => 'BEFORE YOU APPLY',
                'tips' => [['text' => 'Use your organisation’s work email.'], ['text' => 'Keep your registration details nearby.'], ['text' => 'Only upload documents you can share.']],
                'submit_note' => 'Submission does not guarantee partnership approval.',
            ], ['anchor' => 'apply']),
            $this->s('steps', [
                'eyebrow' => 'WHAT HAPPENS NEXT',
                'items' => [
                    ['title' => 'We review your application', 'text' => "Our team checks your organisation details\nand the partnership opportunities."],
                    ['title' => 'We get to know each other', 'text' => "We’ll contact you to discuss your goals,\nservices and how we can work together."],
                    ['title' => 'We build the partnership', 'text' => "Once approved, we agree on the next\nsteps and help you get started."],
                ],
            ], ['background' => 'blush']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function legal(string $title, string $intro): array
    {
        return [
            $this->s('page_hero', ['eyebrow' => 'LEGAL', 'title' => $title, 'description' => $intro], ['background' => 'blush', 'spacing' => 'none']),
            $this->s('rich_text', ['content' => '<p><strong>This page needs your company’s legal text.</strong> Replace this placeholder in Website CMS → Pages before the site goes live.</p><h2>Contact</h2><p>Questions about this policy? Email info@rtlbangladesh.com or call +880 1806 428212.</p>']),
        ];
    }

    /** @return array<string, array{string, list<array<string, mixed>>}> */
    private function services(): array
    {
        $service = fn (string $eyebrow, string $title, string $intro, string $image, string $body, array $steps, ?string $level = null) => [
            $this->s('page_hero', ['eyebrow' => $eyebrow, 'title' => $title, 'description' => $intro, 'button' => $this->link('Book a consultation', '/contact'), 'image' => $this->img($image)], ['background' => 'blush', 'spacing' => 'none']),
            $this->s('image_text', ['eyebrow' => 'HOW WE HELP', 'title' => 'Clear advice at every step.', 'content' => $body, 'image' => $this->img('about-team.jpg'), 'image_side' => 'right', 'link' => $this->link('Talk to an adviser', '/contact')]),
            $this->s('features', ['eyebrow' => 'THE PROCESS', 'title' => 'What working with us looks like.', 'items' => $steps], ['background' => 'sand']),
            ...($level ? [$this->s('pathways', [
                'eyebrow' => 'FIND YOUR DIRECTION',
                'title' => 'Big ambitions. The right pathway.',
                'items' => [
                    ['image' => $this->img('pathway-diploma.jpg'), 'eyebrow' => 'BUILD YOUR FOUNDATION', 'title' => 'Diploma', 'text' => 'Practical skills. A strong foundation.', 'link' => $this->link('Diploma', '/universities?level=diploma')],
                    ['image' => $this->img('pathway-bachelors.jpg'), 'eyebrow' => 'SHAPE YOUR FUTURE', 'title' => 'Bachelor’s', 'text' => 'Find a degree that turns your interests into your next big opportunity.', 'link' => $this->link('Bachelor’s', '/universities?level=bachelor')],
                    ['image' => $this->img('pathway-masters.jpg'), 'eyebrow' => 'TAKE THE NEXT STEP', 'title' => 'Master’s', 'text' => 'Deepen your expertise and take your academic ambitions further.', 'link' => $this->link('Master’s', '/universities?level=master')],
                ],
            ])] : []),
            $this->s('faq', ['eyebrow' => 'HERE TO HELP', 'title' => "Big questions.\nClear answers."], ['background' => 'sand']),
        ];

        $steps = [
            ['title' => 'A first conversation', 'text' => 'We listen to your goals, background and timeline.'],
            ['title' => 'A clear plan', 'text' => 'We explain your options, requirements and costs before you commit.'],
            ['title' => 'Your application', 'text' => 'We help you prepare documents and submit a complete application.'],
        ];

        return [
            'student-visa' => ['Student visa', $service('STUDY ABROAD', "Study abroad.\nWith confidence.", 'From choosing a course and institution to preparing your student visa, we guide you at every step.', 'hero-campus.png', '<p>We help you choose a course and institution that fit your goals and budget, prepare your admission application, and build a complete student visa file.</p><ul><li>Course, institution and destination guidance</li><li>Admission and offer-letter support</li><li>Document checklists and preparation</li><li>Visa application and interview preparation</li></ul>', $steps, 'study')],
            'schooling-visa' => ['Schooling visa', $service('SCHOOLING VISA', "A great school.\nAnywhere.", 'Guidance for families planning school education abroad for their children.', 'pathway-bachelors.jpg', '<p>We help families compare schools and destinations, understand guardianship requirements and prepare the visa application.</p><ul><li>School and destination guidance</li><li>Enrolment support</li><li>Guardian and family visa advice</li></ul>', $steps)],
            'visitor-visa' => ['Visitor visa', $service('VISITOR VISA', "Visit family.\nSee the world.", 'Tourist, family-visit and short business visas, prepared properly the first time.', 'about-hero.jpg', '<p>We help you put together a clear, well-documented visitor visa application, whether you are visiting family, travelling for tourism or attending a short business trip.</p><ul><li>Itinerary and purpose-of-visit guidance</li><li>Financial and supporting documents</li><li>Application review before you submit</li></ul>', $steps)],
            'skilled-migration' => ['Skilled migration', $service('SKILLED MIGRATION', "Your skills.\nA new home.", 'Personalised pathways for skilled professionals considering permanent migration.', 'pathway-masters.jpg', '<p>We assess your qualifications, experience and goals, then explain the skilled migration pathways that may fit you—before you spend money on an application.</p><ul><li>Eligibility assessment</li><li>Skills assessment and document guidance</li><li>Application preparation and lodgement support</li></ul>', $steps)],
            'work-permits' => ['Work permits', $service('WORK PERMITS', "Your next career move.\nAbroad.", 'Support for work visas and permits for your next professional chapter.', 'pathway-diploma.jpg', '<p>We help you understand work visa options, employer requirements and the documents you need, and we prepare your application with you.</p><ul><li>Work visa options for your profession</li><li>Employer and job-offer requirements</li><li>Application and document preparation</li></ul>', $steps)],
        ];
    }

    private function seedMenus(): void
    {
        $page = fn (string $slugOrKey) => Page::query()->where('system_key', $slugOrKey)->orWhere('slug', $slugOrKey)->value('id');

        $menus = [
            'main' => ['Main navigation', [
                ['Home', 'page', 'home'],
                ['Visa services', 'url', '#', [
                    ['Student visa', 'page', 'student-visa'],
                    ['Schooling visa', 'page', 'schooling-visa'],
                    ['Visitor visa', 'page', 'visitor-visa'],
                    ['Skilled migration', 'page', 'skilled-migration'],
                    ['Work permits', 'page', 'work-permits'],
                ]],
                ['All partners', 'page', 'universities'],
                ['About us', 'page', 'about'],
                ['Reviews', 'page', 'reviews'],
                ['Blog', 'page', 'blog'],
            ]],
            'footer' => ['Footer: Explore RTL', [
                ['About us', 'page', 'about'],
                ['Our partners', 'page', 'universities'],
                ['Student reviews', 'page', 'reviews'],
                ['Blog & insights', 'page', 'blog'],
            ]],
            'footer_services' => ['Footer: Our services', [
                ['Student visa', 'page', 'student-visa'],
                ['Schooling visa', 'page', 'schooling-visa'],
                ['Visitor visa', 'page', 'visitor-visa'],
                ['Skilled migration', 'page', 'skilled-migration'],
            ]],
            'footer_legal' => ['Footer: Legal links', [
                ['Privacy policy', 'page', 'privacy'],
                ['Terms & conditions', 'page', 'terms'],
                ['FAQ', 'page', 'faq'],
            ]],
        ];

        foreach ($menus as $location => [$name, $items]) {
            $menu = Menu::firstOrCreate(['location' => $location], ['name' => $name]);

            if ($menu->items()->exists()) {
                continue;
            }

            foreach ($items as $i => $item) {
                $parent = $menu->items()->create($this->menuItem($item, $page, ($i + 1) * 10));

                foreach ($item[3] ?? [] as $j => $child) {
                    $menu->items()->create([...$this->menuItem($child, $page, ($j + 1) * 10), 'parent_id' => $parent->id]);
                }
            }
        }
    }

    /**
     * @param  array<int, mixed>  $item
     * @return array<string, mixed>
     */
    private function menuItem(array $item, \Closure $page, int $sort): array
    {
        [$label, $type, $target] = $item;

        return [
            'label' => $label,
            'link_type' => $type,
            'page_id' => $type === 'page' ? $page($target) : null,
            'url' => $type === 'url' ? $target : null,
            'sort_order' => $sort,
            'visibility' => 'everyone',
            'is_active' => true,
        ];
    }
}
