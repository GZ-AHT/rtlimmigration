<?php

namespace App\Support\Cms;

/**
 * The section types pages are built from (REQ §5 / §21.7, roadmap Q7).
 * Each one matches a Figma section (or a REQ section drawn in the Figma
 * style). The admin editor renders forms from these field definitions and
 * SectionSanitizer enforces them on save, so this file is the single list.
 *
 * Field types: text, textarea, richtext, image, video, link {label, url}, toggle,
 * number, select (options), repeater (fields, max).
 * "Dynamic" sections also pull live records (posts, FAQs, universities…).
 */
class SectionTypes
{
    public const BACKGROUNDS = ['white' => 'White', 'surface' => 'Light grey', 'sand' => 'Sand', 'blush' => 'Blush', 'navy' => 'Navy'];

    public const SPACING = ['default' => 'Normal', 'compact' => 'Compact', 'joined' => 'Joined to the section above', 'none' => 'None'];

    /** @return array<string, array{label: string, icon: string, description: string, dynamic?: string, fields: array<string, array<string, mixed>>}> */
    public static function all(): array
    {
        $heading = [
            'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'max' => 80],
            'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 160, 'rows' => 2],
            'description' => ['type' => 'textarea', 'label' => 'Introduction', 'max' => 400],
        ];
        $link = ['type' => 'link', 'label' => 'Link'];
        $limit = fn (int $default, int $max = 12) => ['type' => 'number', 'label' => 'How many to show', 'min' => 1, 'max' => $max, 'default' => $default];

        // Fields both homepage banner designs share. $cover: the full-width design.
        $banner = fn (bool $cover) => [
            'eyebrow' => $heading['eyebrow'],
            'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 120, 'rows' => 2],
            'title_accent' => ['type' => 'textarea', 'label' => $cover ? 'Heading, second line' : 'Heading, second part (shown in the brand colour)', 'max' => 120, 'rows' => 2],
            'description' => $heading['description'],
            'primary' => ['type' => 'link', 'label' => 'Main button'],
            'secondary' => ['type' => 'link', 'label' => 'Second link'],
            'media_type' => ['type' => 'select', 'label' => 'Background', 'options' => ['image' => 'Image', 'video' => 'Video'], 'default' => 'image'],
            'image' => ['type' => 'image', 'label' => $cover ? 'Background image (also shown while a video loads, or if it can’t play)' : 'Photo (also shown while a video loads, or if it can’t play)'],
            'video' => ['type' => 'video', 'label' => 'Video (MP4 or WebM, up to 50 MB; plays muted on a loop)'],
            'overlay' => ['type' => 'select', 'label' => 'Overlay', 'options' => $cover
                ? ['gradient' => 'Dark, fading to the right (as designed)', 'solid' => 'Dark, even', 'brand' => 'Brand colour', 'none' => 'None']
                : ['none' => 'None (as designed)', 'solid' => 'Dark', 'brand' => 'Brand colour'], 'default' => $cover ? 'gradient' : 'none'],
            'overlay_opacity' => ['type' => 'number', 'label' => 'Overlay strength (%)', 'min' => 0, 'max' => 90, 'default' => $cover ? 70 : 30],
            'text_align' => ['type' => 'select', 'label' => 'Text alignment', 'options' => ['left' => 'Left', 'center' => 'Centre'], 'default' => 'left'],
            'height' => ['type' => 'select', 'label' => 'Banner height on desktop (1024px and wider)', 'options' => $cover
                ? ['standard' => 'Standard (as designed)', 'compact' => 'Compact', 'tall' => 'Tall', 'screen' => 'Fill the screen']
                : ['standard' => 'As designed', 'screen' => 'Fill the screen'], 'default' => 'standard'],
            'height_tablet' => ['type' => 'select', 'label' => 'Banner height on tablets (640 to 1023px)', 'options' => ['auto' => 'Fit the content', 'compact' => 'Compact', 'standard' => 'Standard', 'tall' => 'Tall', 'screen' => 'Fill the screen'], 'default' => 'auto'],
            'height_mobile' => ['type' => 'select', 'label' => 'Banner height on phones (under 640px)', 'options' => ['auto' => 'Fit the content', 'compact' => 'Compact', 'standard' => 'Standard', 'tall' => 'Tall', 'screen' => 'Fill the screen'], 'default' => 'auto'],
            'mobile_video' => ['type' => 'toggle', 'label' => 'Play the video on phones too (off: phones show the image, which saves data)', 'default' => false],
            'show_finder' => ['type' => 'toggle', 'label' => 'Show the pathway finder', 'default' => true],
            'finder_tabs' => ['type' => 'repeater', 'label' => 'Pathway finder tabs (a tab without a link searches universities and courses)', 'max' => 5, 'fields' => [
                'label' => ['type' => 'text', 'label' => 'Tab name', 'max' => 40],
                'url' => ['type' => 'text', 'label' => 'Link (leave blank for the course search)', 'max' => 255],
            ]],
            'finder_help' => ['type' => 'link', 'label' => 'Pathway finder help link'],
        ];

        return [
            // Two homepage banner designs with separate content; the Homepage
            // editor's "Banner design" choice shows one of them.
            'hero' => [
                'label' => 'Hero: classic banner', 'icon' => 'wallpaper', 'description' => 'Design 1: big heading and buttons beside a photo or video. Optional pathway finder and slider.',
                'fields' => [
                    ...$banner(false),
                    'image_caption' => ['type' => 'text', 'label' => 'Photo caption', 'max' => 60],
                    'slides' => ['type' => 'repeater', 'label' => 'Extra slides (turns the hero into a slider)', 'max' => 6, 'fields' => [
                        'image' => ['type' => 'image', 'label' => 'Photo'],
                        'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 120, 'rows' => 2],
                        'description' => ['type' => 'textarea', 'label' => 'Text', 'max' => 300],
                    ]],
                    'mobile_media' => ['type' => 'select', 'label' => 'Photo / video on phones', 'options' => ['show' => 'Show under the text', 'hide' => 'Hide'], 'default' => 'show'],
                ],
            ],
            'hero_cover' => [
                'label' => 'Hero: full-width banner', 'icon' => 'panorama', 'description' => 'Design 2: heading and buttons over a full-width photo or video, with the pathway finder along the bottom.',
                'fields' => [
                    ...$banner(true),
                    'image_caption' => ['type' => 'text', 'label' => 'Small caption over the photo', 'max' => 60],
                ],
            ],
            'pathways' => [
                'label' => 'Education pathways', 'icon' => 'school', 'description' => 'Cards for study levels or services, each with a photo and link.',
                'fields' => [...$heading, 'link' => $link, 'items' => ['type' => 'repeater', 'label' => 'Cards', 'max' => 6, 'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Photo'],
                    'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'max' => 60],
                    'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                    'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 200],
                    'link' => $link,
                ]]],
            ],
            'stats' => [
                'label' => 'Statistics', 'icon' => 'monitoring', 'description' => 'Key figures in a row.',
                'fields' => [
                    'items' => ['type' => 'repeater', 'label' => 'Figures', 'max' => 6, 'fields' => [
                        'value' => ['type' => 'text', 'label' => 'Figure', 'max' => 20],
                        'label' => ['type' => 'text', 'label' => 'Label', 'max' => 60],
                    ]],
                    'note' => ['type' => 'text', 'label' => 'Footnote', 'max' => 120],
                    'link' => $link,
                ],
            ],
            'about' => [
                'label' => 'About with steps', 'icon' => 'groups', 'description' => 'Photo with caption, text and numbered steps.',
                'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Photo'],
                    'caption_eyebrow' => ['type' => 'text', 'label' => 'Photo caption: small heading', 'max' => 60],
                    'caption' => ['type' => 'text', 'label' => 'Photo caption', 'max' => 100],
                    ...$heading,
                    'description' => ['type' => 'textarea', 'label' => 'Text', 'max' => 800, 'rows' => 4],
                    'steps' => ['type' => 'repeater', 'label' => 'Steps', 'max' => 6, 'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                        'text' => ['type' => 'text', 'label' => 'Text', 'max' => 140],
                    ]],
                    'link' => $link,
                ],
            ],
            'testimonials' => [
                'label' => 'Student stories', 'icon' => 'format_quote', 'description' => 'Featured student testimonials.', 'dynamic' => 'Testimonials marked featured (all testimonials on the reviews page)',
                'fields' => [...$heading, 'link' => $link, 'limit' => $limit(3, 9), 'all' => ['type' => 'toggle', 'label' => 'Show every testimonial (for the reviews page)', 'default' => false]],
            ],
            'video' => [
                'label' => 'Video counseling', 'icon' => 'videocam', 'description' => 'Invitation to a video consultation, with a video-call window (photo or video). The button opens the consultation form.',
                'fields' => [
                    ...$heading,
                    'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 120, 'rows' => 3],
                    'button' => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                    'detail' => ['type' => 'text', 'label' => 'Small print under the button', 'max' => 120],
                    'call_style' => ['type' => 'select', 'label' => 'Video window style', 'options' => ['meeting' => 'Video call (Google Meet / Zoom style)', 'simple' => 'Simple (as in the Figma design)'], 'default' => 'meeting'],
                    'media_type' => ['type' => 'select', 'label' => 'In the video window', 'options' => ['image' => 'Image', 'video' => 'Video'], 'default' => 'image'],
                    'image' => ['type' => 'image', 'label' => 'Adviser photo (also shown while a video loads, or if it can’t play)'],
                    'video' => ['type' => 'video', 'label' => 'Adviser video (MP4 or WebM; plays muted on a loop)'],
                    'mobile_video' => ['type' => 'toggle', 'label' => 'Play the video on phones too (off: phones show the photo)', 'default' => false],
                    'adviser_name' => ['type' => 'text', 'label' => 'Name on the adviser’s video', 'max' => 40],
                    'self_image' => ['type' => 'image', 'label' => '“You” tile photo (optional; blank shows a letter)'],
                    'show_timer' => ['type' => 'toggle', 'label' => 'Show a running call timer (video call style)', 'default' => true],
                    'room_label' => ['type' => 'text', 'label' => 'Video window label', 'max' => 60],
                    'reassurance' => ['type' => 'text', 'label' => 'Line under the video window', 'max' => 120],
                    'steps' => ['type' => 'repeater', 'label' => 'Steps', 'max' => 3, 'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                        'text' => ['type' => 'text', 'label' => 'Text', 'max' => 120],
                    ]],
                ],
            ],
            'blog' => [
                'label' => 'Latest articles', 'icon' => 'article', 'description' => 'Newest blog posts (featured first).', 'dynamic' => 'Published blog posts',
                'fields' => [
                    ...$heading,
                    'link' => $link,
                    'limit' => $limit(3, 9),
                    'category' => ['type' => 'text', 'label' => 'Only this category (its slug, e.g. study-destinations; blank for all)', 'max' => 120],
                    'all' => ['type' => 'toggle', 'label' => 'Full blog listing with categories, search and pages (for the blog page)', 'default' => false],
                    'featured_label' => ['type' => 'text', 'label' => 'Full listing: label on the featured article', 'max' => 40],
                    'featured_note' => ['type' => 'text', 'label' => 'Full listing: small print on the featured article', 'max' => 140],
                    'featured_button' => ['type' => 'text', 'label' => 'Full listing: featured article button', 'max' => 40],
                ],
            ],
            'faq' => [
                'label' => 'FAQs', 'icon' => 'help', 'description' => 'Questions by category with expanding answers.', 'dynamic' => 'FAQs marked "show on homepage" (all FAQs on the FAQ page)',
                'fields' => [...$heading, 'all' => ['type' => 'toggle', 'label' => 'Show every FAQ (for the FAQ page), with search', 'default' => false]],
            ],
            'universities' => [
                'label' => 'Universities', 'icon' => 'account_balance', 'description' => 'Featured partner universities.', 'dynamic' => 'Featured, active universities',
                'fields' => [...$heading, 'link' => $link, 'limit' => $limit(6, 12), 'all' => ['type' => 'toggle', 'label' => 'Searchable list of every university with country and study-level filters (the pathway finder results)', 'default' => false]],
            ],
            'countries' => [
                'label' => 'Popular countries', 'icon' => 'public', 'description' => 'Study destinations with university counts.', 'dynamic' => 'Destination countries',
                'fields' => [...$heading, 'limit' => $limit(8, 16)],
            ],
            'courses' => [
                'label' => 'Featured courses', 'icon' => 'menu_book', 'description' => 'Courses marked featured.', 'dynamic' => 'Featured, active courses',
                'fields' => [...$heading, 'link' => $link, 'limit' => $limit(6, 12)],
            ],
            'logos' => [
                'label' => 'Partner logos', 'icon' => 'handshake', 'description' => 'A row of partner or university logos.',
                'fields' => [...$heading, 'items' => ['type' => 'repeater', 'label' => 'Logos', 'max' => 24, 'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Logo'],
                    'name' => ['type' => 'text', 'label' => 'Name', 'max' => 80],
                    'url' => ['type' => 'text', 'label' => 'Link (optional)', 'max' => 255],
                ]]],
            ],
            'newsletter' => [
                'label' => 'Newsletter', 'icon' => 'mark_email_read', 'description' => 'Email sign-up box.',
                'fields' => [...$heading, 'button' => ['type' => 'text', 'label' => 'Button text', 'max' => 30], 'note' => ['type' => 'textarea', 'label' => 'Small print under the form', 'max' => 200, 'rows' => 2]],
            ],
            'contact' => [
                'label' => 'Consultation form', 'icon' => 'contact_mail', 'description' => 'The enquiry form; every submission becomes a lead.',
                'fields' => [
                    ...$heading,
                    'aside_eyebrow' => ['type' => 'text', 'label' => 'Side panel: small heading', 'max' => 60],
                    'aside_title' => ['type' => 'textarea', 'label' => 'Side panel: heading', 'max' => 120, 'rows' => 2],
                    'steps' => ['type' => 'repeater', 'label' => 'Side panel: what happens next', 'max' => 4, 'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                        'text' => ['type' => 'text', 'label' => 'Text', 'max' => 140],
                    ]],
                    'note_title' => ['type' => 'text', 'label' => 'Side panel: closing heading', 'max' => 80],
                    'note_text' => ['type' => 'text', 'label' => 'Side panel: closing text', 'max' => 200],
                    'show_details' => ['type' => 'toggle', 'label' => 'Show phone, email and address in the side panel', 'default' => true],
                ],
            ],
            'partner_form' => [
                'label' => 'Partner application form', 'icon' => 'badge', 'description' => 'Form for agents and institutions to apply as partners, with a sidebar (benefits, contact, tips).',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Form heading', 'max' => 80],
                    'description' => ['type' => 'textarea', 'label' => 'Form introduction', 'max' => 300],
                    'aside_eyebrow' => ['type' => 'text', 'label' => 'Dark panel: small heading', 'max' => 60],
                    'aside_title' => ['type' => 'textarea', 'label' => 'Dark panel: heading', 'max' => 120, 'rows' => 2],
                    'benefits' => ['type' => 'repeater', 'label' => 'Dark panel: benefits', 'max' => 6, 'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                        'text' => ['type' => 'text', 'label' => 'Text', 'max' => 160],
                    ]],
                    'question_title' => ['type' => 'text', 'label' => 'Contact box: heading (email and phone come from Site settings)', 'max' => 80],
                    'question_text' => ['type' => 'textarea', 'label' => 'Contact box: text', 'max' => 200],
                    'tips_title' => ['type' => 'text', 'label' => 'Tips: small heading', 'max' => 60],
                    'tips' => ['type' => 'repeater', 'label' => 'Tips', 'max' => 6, 'fields' => [
                        'text' => ['type' => 'text', 'label' => 'Tip', 'max' => 120],
                    ]],
                    'submit_note' => ['type' => 'text', 'label' => 'Small print beside the submit button', 'max' => 120],
                ],
            ],
            'steps' => [
                'label' => 'Numbered steps', 'icon' => 'format_list_numbered', 'description' => 'A row of numbered steps, e.g. "What happens next".',
                'fields' => [...$heading, 'items' => ['type' => 'repeater', 'label' => 'Steps', 'max' => 6, 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                    'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 200],
                ]]],
            ],
            'skill_search' => [
                'label' => 'Skill migration search', 'icon' => 'travel_explore', 'description' => 'Search box where visitors pick a destination and start its skill migration assessment.', 'dynamic' => 'Published country assessments',
                'fields' => [
                    ...$heading,
                    'placeholder' => ['type' => 'text', 'label' => 'Search box text', 'max' => 80],
                    'button' => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                    'link' => ['type' => 'link', 'label' => 'Link under the search (e.g. all countries)'],
                ],
            ],
            'page_intro' => [
                'label' => 'Simple page header', 'icon' => 'short_text', 'description' => 'Breadcrumb, large heading and introduction, with an optional note on the right (Blog page).',
                'fields' => [
                    ...$heading,
                    'parent' => ['type' => 'link', 'label' => 'Breadcrumb: page above this one (optional, e.g. Partners)'],
                    'aside' => ['type' => 'link', 'label' => 'Note on the right (with optional link)'],
                    'aside_eyebrow' => ['type' => 'text', 'label' => 'Or a panel on the right: small heading', 'max' => 60],
                    'aside_title' => ['type' => 'textarea', 'label' => 'Panel on the right: heading', 'max' => 120, 'rows' => 2],
                    'aside_text' => ['type' => 'textarea', 'label' => 'Panel on the right: text', 'max' => 240],
                ],
            ],
            'page_hero' => [
                'label' => 'Page header', 'icon' => 'title', 'description' => 'Breadcrumb, big heading, text, button and a photo with a coloured panel (About page style).',
                'fields' => [
                    ...$heading,
                    'button' => ['type' => 'link', 'label' => 'Button'],
                    'footnote' => ['type' => 'text', 'label' => 'Small print under the button', 'max' => 80],
                    'image' => ['type' => 'image', 'label' => 'Photo (optional)'],
                ],
            ],
            'story' => [
                'label' => 'Story with list', 'icon' => 'auto_stories', 'description' => 'Heading and paragraphs beside a numbered list (About page "Our story").',
                'fields' => [
                    ...$heading,
                    'description' => ['type' => 'textarea', 'label' => 'First paragraph', 'max' => 600, 'rows' => 3],
                    'text' => ['type' => 'textarea', 'label' => 'Second paragraph', 'max' => 600, 'rows' => 3],
                    'list_title' => ['type' => 'text', 'label' => 'List heading', 'max' => 80],
                    'items' => ['type' => 'repeater', 'label' => 'List', 'max' => 8, 'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                        'text' => ['type' => 'text', 'label' => 'Text', 'max' => 160],
                    ]],
                ],
            ],
            'mission' => [
                'label' => 'Two panels', 'icon' => 'splitscreen', 'description' => 'Two large panels side by side (mission and vision), with a row of values underneath.',
                'fields' => [
                    'eyebrow' => $heading['eyebrow'],
                    'title' => $heading['title'],
                    'cards' => ['type' => 'repeater', 'label' => 'Panels', 'max' => 2, 'fields' => [
                        'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'max' => 60],
                        'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 80, 'rows' => 2],
                        'text' => ['type' => 'textarea', 'label' => 'First paragraph', 'max' => 400],
                        'text2' => ['type' => 'textarea', 'label' => 'Second paragraph', 'max' => 400],
                    ]],
                    'values' => ['type' => 'repeater', 'label' => 'Values', 'max' => 6, 'fields' => [
                        'label' => ['type' => 'text', 'label' => 'Value', 'max' => 40],
                    ]],
                ],
            ],
            'features' => [
                'label' => 'Numbered cards', 'icon' => 'view_module', 'description' => 'A grid of numbered cards (credentials, benefits, reasons to choose you).',
                'fields' => [...$heading, 'items' => ['type' => 'repeater', 'label' => 'Cards', 'max' => 9, 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                    'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 220],
                ]]],
            ],
            'gallery' => [
                'label' => 'Gallery', 'icon' => 'photo_library', 'description' => 'Tall image cards with captions (success stories, certificates).',
                'fields' => [...$heading, 'items' => ['type' => 'repeater', 'label' => 'Images', 'max' => 12, 'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Image'],
                    'caption' => ['type' => 'text', 'label' => 'Caption', 'max' => 80],
                ]]],
            ],
            'message' => [
                'label' => 'Message with portrait', 'icon' => 'record_voice_over', 'description' => 'Portrait beside a message, highlight line and button (leadership message).',
                'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Portrait'],
                    ...$heading,
                    'description' => ['type' => 'textarea', 'label' => 'First paragraph', 'max' => 600, 'rows' => 3],
                    'text' => ['type' => 'textarea', 'label' => 'Second paragraph', 'max' => 600, 'rows' => 3],
                    'highlight' => ['type' => 'text', 'label' => 'Highlight line', 'max' => 80],
                    'detail' => ['type' => 'text', 'label' => 'Small print', 'max' => 140],
                    'button' => ['type' => 'link', 'label' => 'Button'],
                ],
            ],
            'contact_hero' => [
                'label' => 'Page header with contact cards', 'icon' => 'contact_phone', 'description' => 'Breadcrumb, two-tone heading, photo with caption and call / email / WhatsApp cards (Contact page).',
                'fields' => [
                    'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'max' => 80],
                    'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 120, 'rows' => 2],
                    'title_accent' => ['type' => 'textarea', 'label' => 'Heading, second part (shown in the brand colour)', 'max' => 120, 'rows' => 2],
                    'description' => ['type' => 'textarea', 'label' => 'Introduction', 'max' => 400],
                    'tags' => ['type' => 'text', 'label' => 'Topics line (e.g. STUDY ABROAD / VISA GUIDANCE)', 'max' => 120],
                    'image' => ['type' => 'image', 'label' => 'Photo'],
                    'image_caption' => ['type' => 'link', 'label' => 'Photo caption (with optional link)'],
                    'cards' => ['type' => 'repeater', 'label' => 'Contact cards', 'max' => 3, 'fields' => [
                        'kind' => ['type' => 'select', 'label' => 'Type', 'options' => ['phone' => 'Phone (from Site settings)', 'email' => 'Email (from Site settings)', 'whatsapp' => 'WhatsApp (from Site settings)', 'link' => 'Other link'], 'default' => 'phone'],
                        'label' => ['type' => 'text', 'label' => 'Small heading', 'max' => 40],
                        'title' => ['type' => 'text', 'label' => 'Title (blank shows the phone number or email)', 'max' => 80],
                        'text' => ['type' => 'text', 'label' => 'Text', 'max' => 140],
                        'link_label' => ['type' => 'text', 'label' => 'Link text', 'max' => 40],
                        'url' => ['type' => 'text', 'label' => 'Link (only for "Other link")', 'max' => 255],
                    ]],
                ],
            ],
            'visit' => [
                'label' => 'Visit us with map', 'icon' => 'map', 'description' => 'Office address with a map, a Google Maps link and tips for the visit.',
                'fields' => [
                    'eyebrow' => ['type' => 'text', 'label' => 'Small heading', 'max' => 80],
                    'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 120, 'rows' => 2],
                    'address' => ['type' => 'text', 'label' => 'Address (blank uses Site settings → General)', 'max' => 200],
                    'note' => ['type' => 'text', 'label' => 'Text under the address', 'max' => 200],
                    'latitude' => ['type' => 'text', 'label' => 'Map latitude (e.g. 23.7806)', 'max' => 20],
                    'longitude' => ['type' => 'text', 'label' => 'Map longitude (e.g. 90.4167)', 'max' => 20],
                    'map_link' => ['type' => 'link', 'label' => 'Map button (blank link searches Google Maps for the address)'],
                    'tips_title' => ['type' => 'text', 'label' => 'Tips heading', 'max' => 80],
                    'tips' => ['type' => 'repeater', 'label' => 'Tips', 'max' => 4, 'fields' => [
                        'text' => ['type' => 'text', 'label' => 'Tip', 'max' => 120],
                    ]],
                    'tips_note' => ['type' => 'text', 'label' => 'Small print under the tips', 'max' => 160],
                ],
            ],
            'cta' => [
                'label' => 'Call to action', 'icon' => 'campaign', 'description' => 'A band with a heading and button.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                    'description' => ['type' => 'text', 'label' => 'Text', 'max' => 200],
                    'button' => ['type' => 'link', 'label' => 'Button'],
                ],
            ],
            'image_text' => [
                'label' => 'Image and text', 'icon' => 'view_agenda', 'description' => 'Photo beside formatted text.',
                'fields' => [
                    'eyebrow' => $heading['eyebrow'],
                    'title' => $heading['title'],
                    'content' => ['type' => 'richtext', 'label' => 'Text'],
                    'image' => ['type' => 'image', 'label' => 'Photo'],
                    'image_side' => ['type' => 'select', 'label' => 'Photo side', 'options' => ['left' => 'Left', 'right' => 'Right'], 'default' => 'left'],
                    'link' => $link,
                ],
            ],
            'rich_text' => [
                'label' => 'Text', 'icon' => 'notes', 'description' => 'Formatted text: headings, lists, links, images.',
                'fields' => ['content' => ['type' => 'richtext', 'label' => 'Text']],
            ],
        ];
    }

    /**
     * Media ids used by these sections, found by field type (image, video)
     * rather than field name, so fields like "self_image" count too.
     *
     * @param  array<int, mixed>  $sections
     * @return list<int>
     */
    public static function mediaIds(array $sections): array
    {
        $ids = [];
        $walk = function (array $fields, array $data) use (&$walk, &$ids): void {
            foreach ($fields as $key => $field) {
                $value = $data[$key] ?? null;

                if (in_array($field['type'], ['image', 'video'], true) && is_numeric($value)) {
                    $ids[] = (int) $value;
                } elseif ($field['type'] === 'repeater' && is_array($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $walk($field['fields'], $item);
                        }
                    }
                }
            }
        };

        foreach ($sections as $section) {
            $type = is_array($section) && is_string($section['type'] ?? null) ? self::get($section['type']) : null;

            if ($type !== null && is_array($section['data'] ?? null)) {
                $walk($type['fields'], $section['data']);
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return array<string, mixed>|null */
    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    /**
     * Defaults for a new section of this type.
     *
     * @return array<string, mixed>
     */
    public static function defaults(string $type): array
    {
        $data = [];

        foreach (self::get($type)['fields'] ?? [] as $key => $field) {
            $data[$key] = match ($field['type']) {
                'toggle' => (bool) ($field['default'] ?? false),
                'number' => (int) ($field['default'] ?? 0),
                'select' => (string) ($field['default'] ?? array_key_first($field['options'])),
                'repeater' => [],
                'link' => ['label' => '', 'url' => ''],
                'image', 'video' => null,
                default => '',
            };
        }

        return $data;
    }
}
