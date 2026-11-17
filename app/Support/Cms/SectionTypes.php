<?php

namespace App\Support\Cms;

/**
 * The section types pages are built from (REQ §5 / §21.7, roadmap Q7).
 * Each one matches a Figma section (or a REQ section drawn in the Figma
 * style). The admin editor renders forms from these field definitions and
 * SectionSanitizer enforces them on save, so this file is the single list.
 *
 * Field types: text, textarea, richtext, image, link {label, url}, toggle,
 * number, select (options), repeater (fields, max).
 * "Dynamic" sections also pull live records (posts, FAQs, universities…).
 */
class SectionTypes
{
    public const BACKGROUNDS = ['white' => 'White', 'surface' => 'Light grey', 'sand' => 'Sand', 'blush' => 'Blush', 'navy' => 'Navy'];

    public const SPACING = ['default' => 'Normal', 'compact' => 'Compact', 'none' => 'None'];

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

        return [
            'hero' => [
                'label' => 'Hero', 'icon' => 'wallpaper', 'description' => 'Big heading, buttons and photo. Optional pathway finder and slider.',
                'fields' => [
                    ...$heading,
                    'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 120, 'rows' => 2],
                    'primary' => ['type' => 'link', 'label' => 'Main button'],
                    'secondary' => ['type' => 'link', 'label' => 'Second link'],
                    'image' => ['type' => 'image', 'label' => 'Photo'],
                    'image_caption' => ['type' => 'text', 'label' => 'Photo caption', 'max' => 60],
                    'show_finder' => ['type' => 'toggle', 'label' => 'Show the pathway finder', 'default' => true],
                    'slides' => ['type' => 'repeater', 'label' => 'Extra slides (turns the hero into a slider)', 'max' => 6, 'fields' => [
                        'image' => ['type' => 'image', 'label' => 'Photo'],
                        'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 120, 'rows' => 2],
                        'description' => ['type' => 'textarea', 'label' => 'Text', 'max' => 300],
                    ]],
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
                'label' => 'Student stories', 'icon' => 'format_quote', 'description' => 'Featured student testimonials.', 'dynamic' => 'Testimonials marked featured',
                'fields' => [...$heading, 'link' => $link, 'limit' => $limit(3, 9)],
            ],
            'video' => [
                'label' => 'Video counseling', 'icon' => 'videocam', 'description' => 'Invitation to a video consultation. The button opens the consultation form.',
                'fields' => [
                    ...$heading,
                    'title' => ['type' => 'textarea', 'label' => 'Heading', 'max' => 120, 'rows' => 3],
                    'button' => ['type' => 'text', 'label' => 'Button text', 'max' => 40],
                    'detail' => ['type' => 'text', 'label' => 'Small print under the button', 'max' => 120],
                    'image' => ['type' => 'image', 'label' => 'Adviser photo'],
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
                'fields' => [...$heading, 'link' => $link, 'limit' => $limit(3, 9)],
            ],
            'faq' => [
                'label' => 'FAQs', 'icon' => 'help', 'description' => 'Questions by category with expanding answers.', 'dynamic' => 'FAQs marked "show on homepage" (all FAQs on the FAQ page)',
                'fields' => [...$heading, 'all' => ['type' => 'toggle', 'label' => 'Show every FAQ (for the FAQ page), with search', 'default' => false]],
            ],
            'universities' => [
                'label' => 'Universities', 'icon' => 'account_balance', 'description' => 'Featured partner universities.', 'dynamic' => 'Featured, active universities',
                'fields' => [...$heading, 'link' => $link, 'limit' => $limit(6, 12)],
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
                'fields' => [...$heading, 'button' => ['type' => 'text', 'label' => 'Button text', 'max' => 30]],
            ],
            'contact' => [
                'label' => 'Consultation form', 'icon' => 'contact_mail', 'description' => 'The enquiry form; every submission becomes a lead.',
                'fields' => [...$heading, 'show_details' => ['type' => 'toggle', 'label' => 'Show phone, email and address next to the form', 'default' => true]],
            ],
            'partner_form' => [
                'label' => 'Partner application form', 'icon' => 'badge', 'description' => 'Form for agents and institutions to apply as partners.',
                'fields' => [...$heading, 'benefits' => ['type' => 'repeater', 'label' => 'Benefits', 'max' => 6, 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title', 'max' => 60],
                    'text' => ['type' => 'text', 'label' => 'Text', 'max' => 160],
                ]]],
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
                'image' => null,
                default => '',
            };
        }

        return $data;
    }
}
