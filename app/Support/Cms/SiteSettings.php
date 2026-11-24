<?php

namespace App\Support\Cms;

use App\Models\Media;
use App\Support\Settings\SettingsRepository;
use Illuminate\Validation\Rule;

/**
 * Website settings edited at Website CMS → Site settings (REQ §21.1 General,
 * §21.2 Header, §21.4 Footer, §21.5 Logo & Branding, §21.6 Theme). Defined
 * once here: the admin form, validation and the website all read it.
 * Everything here is public (it ends up on the website) except custom code,
 * which only Super Admin edits (roadmap Q16).
 */
class SiteSettings
{
    /** Fonts the website can load (Google Fonts); the Figma design uses Inter. */
    public const FONTS = ['Inter' => 'Inter', 'DM Sans' => 'DM Sans', 'Poppins' => 'Poppins', 'Manrope' => 'Manrope', 'Plus Jakarta Sans' => 'Plus Jakarta Sans', 'Hind Siliguri' => 'Hind Siliguri'];

    /** @return array<string, array{label: string, description: string, fields: array<string, array<string, mixed>>}> */
    public static function groups(): array
    {
        return [
            'general' => [
                'label' => 'General', 'description' => 'Name, contact details and regional settings used across the website.',
                'fields' => [
                    'site_name' => ['type' => 'text', 'label' => 'Website name', 'max' => 80, 'required' => true],
                    'tagline' => ['type' => 'text', 'label' => 'Tagline', 'max' => 80],
                    'site_url' => ['type' => 'url', 'label' => 'Website URL'],
                    'description' => ['type' => 'textarea', 'label' => 'Description', 'max' => 300],
                    'contact_email' => ['type' => 'email', 'label' => 'Contact email'],
                    'phone' => ['type' => 'text', 'label' => 'Phone number', 'max' => 40],
                    'whatsapp' => ['type' => 'text', 'label' => 'WhatsApp number', 'max' => 40],
                    'address' => ['type' => 'text', 'label' => 'Office address', 'max' => 200],
                    'business_hours' => ['type' => 'text', 'label' => 'Business hours', 'max' => 120],
                    'copyright' => ['type' => 'text', 'label' => 'Copyright text', 'max' => 160, 'hint' => '{year} becomes the current year.'],
                    'timezone' => ['type' => 'select', 'label' => 'Timezone', 'options' => ['Asia/Dhaka' => 'Asia/Dhaka (GMT+6)', 'UTC' => 'UTC']],
                    'date_format' => ['type' => 'select', 'label' => 'Date format', 'options' => ['d M Y' => '17 Nov 2026', 'd/m/Y' => '17/11/2026', 'M d, Y' => 'Nov 17, 2026', 'Y-m-d' => '2026-11-17']],
                    'currency' => ['type' => 'text', 'label' => 'Currency code', 'max' => 3],
                    'currency_symbol' => ['type' => 'text', 'label' => 'Currency symbol', 'max' => 4],
                    'default_language' => ['type' => 'select', 'label' => 'Website language', 'options' => ['en' => 'English'], 'hint' => 'Bangla can be added later (roadmap Q4).'],
                ],
            ],
            'header' => [
                'label' => 'Header', 'description' => 'Top bar, sticky header, call-to-action and login button.',
                'fields' => [
                    'top_bar' => ['type' => 'toggle', 'label' => 'Show the top bar (email, phone, address, login)'],
                    'sticky' => ['type' => 'toggle', 'label' => 'Keep the header visible while scrolling'],
                    'show_login' => ['type' => 'toggle', 'label' => 'Show the login link'],
                    'cta' => ['type' => 'link', 'label' => 'Header button'],
                ],
            ],
            'footer' => [
                'label' => 'Footer', 'description' => 'Consultation band, footer text, newsletter and payment logos. Footer links are edited in Menus.',
                'fields' => [
                    'cta_title' => ['type' => 'text', 'label' => 'Consultation band heading', 'max' => 120],
                    'cta_text' => ['type' => 'text', 'label' => 'Consultation band text', 'max' => 200],
                    'cta_button' => ['type' => 'link', 'label' => 'Consultation band button'],
                    'description' => ['type' => 'textarea', 'label' => 'Footer text', 'max' => 300],
                    'show_newsletter' => ['type' => 'toggle', 'label' => 'Show newsletter sign-up in the footer'],
                    'payment_logos' => ['type' => 'images', 'label' => 'Payment logos', 'max' => 10],
                ],
            ],
            'social' => [
                'label' => 'Social media', 'description' => 'Links shown in the header and footer. Leave blank to hide.',
                'fields' => [
                    'facebook' => ['type' => 'url', 'label' => 'Facebook'],
                    'instagram' => ['type' => 'url', 'label' => 'Instagram'],
                    'linkedin' => ['type' => 'url', 'label' => 'LinkedIn'],
                    'youtube' => ['type' => 'url', 'label' => 'YouTube'],
                    'x' => ['type' => 'url', 'label' => 'X (Twitter)'],
                    'tiktok' => ['type' => 'url', 'label' => 'TikTok'],
                ],
            ],
            'seo' => [
                'label' => 'SEO', 'description' => 'Search and sharing defaults, structured data, verification codes, sitemap and robots.txt (REQ §22). Pages, posts and universities can override these.',
                'fields' => [
                    'enabled' => ['type' => 'toggle', 'label' => 'Turn on SEO features (meta tags, social tags, structured data, analytics)'],
                    'noindex_site' => ['type' => 'toggle', 'label' => 'Hide the whole website from search engines', 'hint' => 'Use on a test site. Adds noindex to every page and blocks crawlers in robots.txt.'],
                    'default_title' => ['type' => 'text', 'label' => 'Default meta title', 'max' => 70, 'hint' => 'Used for the homepage when it has no title of its own.'],
                    'default_description' => ['type' => 'textarea', 'label' => 'Default meta description', 'max' => 170, 'hint' => 'Used when a page has none. Blank uses the general description.'],
                    'default_keywords' => ['type' => 'text', 'label' => 'Default meta keywords', 'max' => 255],
                    'canonical_base' => ['type' => 'url', 'label' => 'Default canonical URL (site address)', 'hint' => 'Canonical links use this address plus the page path. Blank uses the address the page was opened on.'],
                    'default_robots' => ['type' => 'select', 'label' => 'Default robots meta', 'options' => ['index,follow' => 'Index, follow', 'index,nofollow' => 'Index, nofollow', 'noindex,follow' => 'Noindex, follow', 'noindex,nofollow' => 'Noindex, nofollow']],
                    'og_title' => ['type' => 'text', 'label' => 'Default Open Graph title', 'max' => 100],
                    'og_description' => ['type' => 'textarea', 'label' => 'Default Open Graph description', 'max' => 300],
                    'og_image' => ['type' => 'image', 'label' => 'Default Open Graph image (1200 × 630 px)'],
                    'twitter_card' => ['type' => 'select', 'label' => 'Twitter card', 'options' => ['summary_large_image' => 'Large image', 'summary' => 'Summary']],
                    'twitter_site' => ['type' => 'text', 'label' => 'Twitter / X username', 'max' => 16, 'hint' => 'Without the @.'],
                    'publisher' => ['type' => 'text', 'label' => 'Website publisher', 'max' => 120, 'hint' => 'The organisation that owns the website, e.g. RTBL Group.'],
                    'schema_organization' => ['type' => 'toggle', 'label' => 'Organization and website structured data (homepage)'],
                    'schema_local_business' => ['type' => 'toggle', 'label' => 'Local business structured data (homepage)', 'hint' => 'Uses the address, phone, hours and social links from General and Social media.'],
                    'business_type' => ['type' => 'select', 'label' => 'Local business type', 'options' => ['ProfessionalService' => 'Professional service', 'TravelAgency' => 'Travel agency', 'EducationalOrganization' => 'Educational organisation']],
                    'price_range' => ['type' => 'text', 'label' => 'Price range (optional)', 'max' => 20, 'hint' => 'For example ৳৳.'],
                    'hreflang' => ['type' => 'toggle', 'label' => 'Add hreflang tags', 'hint' => 'Marks the website language. More languages can be added later (roadmap Q4).'],
                    'google_verification' => ['type' => 'text', 'label' => 'Google Search Console verification code', 'max' => 100, 'pattern' => '/^[A-Za-z0-9_-]+$/'],
                    'bing_verification' => ['type' => 'text', 'label' => 'Bing Webmaster verification code', 'max' => 100, 'pattern' => '/^[A-Za-z0-9_-]+$/'],
                    'ga_id' => ['type' => 'text', 'label' => 'Google Analytics measurement ID', 'max' => 20, 'pattern' => '/^G-[A-Z0-9]{4,}$/', 'hint' => 'Looks like G-XXXXXXXXXX.'],
                    'gtm_id' => ['type' => 'text', 'label' => 'Google Tag Manager ID', 'max' => 20, 'pattern' => '/^GTM-[A-Z0-9]{4,}$/', 'hint' => 'Looks like GTM-XXXXXXX.'],
                    'sitemap_enabled' => ['type' => 'toggle', 'label' => 'Publish the XML sitemap (/sitemap.xml)'],
                    'sitemap_extra' => ['type' => 'textarea', 'label' => 'Extra sitemap addresses', 'max' => 4000, 'hint' => 'One path or address per line, for pages that are not in the CMS.'],
                    'robots_txt' => ['type' => 'code', 'label' => 'robots.txt', 'max' => 5000, 'hint' => 'Blank uses a safe default that allows crawling and points to the sitemap.'],
                ],
            ],
            'branding' => [
                'label' => 'Logo & branding', 'description' => 'Logos, icons, colours and fonts. Without a logo image, the site name is shown in the brand style.',
                'fields' => [
                    'logo' => ['type' => 'image', 'label' => 'Main logo'],
                    'logo_light' => ['type' => 'image', 'label' => 'Light logo (for dark backgrounds, e.g. the footer)'],
                    'logo_mobile' => ['type' => 'image', 'label' => 'Mobile logo'],
                    'logo_login' => ['type' => 'image', 'label' => 'Login page logo'],
                    'logo_admin' => ['type' => 'image', 'label' => 'Admin panel logo'],
                    'favicon' => ['type' => 'image', 'label' => 'Favicon (square, at least 48 px)'],
                    'apple_icon' => ['type' => 'image', 'label' => 'Apple touch icon (180 × 180 px)'],
                    'primary_color' => ['type' => 'color', 'label' => 'Primary colour'],
                    'secondary_color' => ['type' => 'color', 'label' => 'Secondary colour'],
                    'accent_color' => ['type' => 'color', 'label' => 'Accent colour'],
                    'heading_font' => ['type' => 'select', 'label' => 'Heading font', 'options' => self::FONTS],
                    'body_font' => ['type' => 'select', 'label' => 'Body font', 'options' => self::FONTS],
                ],
            ],
            'theme' => [
                'label' => 'Theme', 'description' => 'Appearance. The website is light-only, as in the Figma design (roadmap Q9); the admin panel supports light, dark and system.',
                'fields' => [
                    'admin_mode' => ['type' => 'select', 'label' => 'Default admin appearance', 'options' => ['system' => 'Follow the device', 'light' => 'Light', 'dark' => 'Dark']],
                    'button_style' => ['type' => 'select', 'label' => 'Button corners', 'options' => ['rounded' => 'Rounded (as designed)', 'pill' => 'Pill', 'square' => 'Square']],
                ],
            ],
            'code' => [
                'label' => 'Custom code', 'description' => 'Added to every website page. Super Admin only; every change is logged. Use for analytics or chat snippets from trusted providers only.', 'permission' => 'cms.custom-code',
                'fields' => [
                    'css' => ['type' => 'code', 'label' => 'Custom CSS', 'max' => 20000],
                    'head_js' => ['type' => 'code', 'label' => 'Code before </head> (e.g. tracking scripts)', 'max' => 20000],
                    'body_js' => ['type' => 'code', 'label' => 'Code before </body>', 'max' => 20000],
                ],
            ],
        ];
    }

    /**
     * Values from the Figma design (roadmap Q15: brand name is a setting).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'header.top_bar' => true, 'header.sticky' => true, 'header.show_login' => true,
            'header.cta' => ['label' => 'Apply now', 'url' => '/contact'],
            'footer.cta_title' => 'Your next chapter starts with a conversation.',
            'footer.cta_text' => 'Let’s find the right way forward, together.',
            'footer.cta_button' => ['label' => 'Book a consultation', 'url' => '/contact'],
            'footer.description' => "Personal guidance for your global journey.\nA concern of RTBL Group.",
            'footer.show_newsletter' => false,
            'footer.payment_logos' => [],
            'general.copyright' => '© {year} RTL Bangladesh. All rights reserved.',
            'general.site_url' => '',
            'general.business_hours' => 'Sat–Thu, 10:00–19:00',
            'branding.primary_color' => '#d71920', 'branding.secondary_color' => '#191b20', 'branding.accent_color' => '#f6f3ee',
            'branding.heading_font' => 'Inter', 'branding.body_font' => 'Inter',
            'seo.enabled' => true, 'seo.noindex_site' => false, 'seo.default_robots' => 'index,follow',
            'seo.twitter_card' => 'summary_large_image', 'seo.schema_organization' => true, 'seo.schema_local_business' => true,
            'seo.business_type' => 'ProfessionalService', 'seo.hreflang' => true, 'seo.sitemap_enabled' => true,
            'seo.default_title' => 'RTL Bangladesh | Immigration & Visa Advisory',
            'seo.publisher' => 'RTBL Group',
            'theme.admin_mode' => 'system', 'theme.button_style' => 'rounded',
        ];
    }

    public function __construct(private SettingsRepository $settings) {}

    /** @return array<string, array<string, mixed>> group => field => value (images as {id,url}) */
    public function forAdmin(bool $withCode): array
    {
        $out = [];
        $mediaIds = [];

        foreach (self::groups() as $group => $def) {
            if ($group === 'code' && ! $withCode) {
                continue;
            }

            foreach ($def['fields'] as $key => $field) {
                $value = $this->settings->get("{$group}.{$key}", self::defaults()["{$group}.{$key}"] ?? null);
                $out[$group][$key] = $value;

                if ($field['type'] === 'image' && is_numeric($value)) {
                    $mediaIds[] = (int) $value;
                } elseif ($field['type'] === 'images') {
                    array_push($mediaIds, ...array_map('intval', (array) $value));
                }
            }
        }

        $media = Media::query()->whereKey($mediaIds)->get()->keyBy('id');

        foreach (self::groups() as $group => $def) {
            foreach ($def['fields'] as $key => $field) {
                if (! isset($out[$group])) {
                    continue;
                }
                if ($field['type'] === 'image') {
                    $out[$group][$key] = is_numeric($out[$group][$key]) ? $media->get((int) $out[$group][$key])?->present() : null;
                } elseif ($field['type'] === 'images') {
                    $out[$group][$key] = array_values(array_filter(array_map(fn ($id) => $media->get((int) $id)?->present(), (array) $out[$group][$key])));
                }
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $values validated */
    public function save(string $group, array $values): void
    {
        foreach (self::groups()[$group]['fields'] as $key => $field) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = match ($field['type']) {
                'toggle' => (bool) $values[$key],
                'image' => $values[$key] !== null ? (int) $values[$key] : null,
                'images' => array_values(array_map('intval', (array) $values[$key])),
                'link' => ['label' => trim((string) ($values[$key]['label'] ?? '')), 'url' => trim((string) ($values[$key]['url'] ?? ''))],
                'color' => strtolower((string) $values[$key]),
                default => $values[$key] === null ? '' : trim((string) $values[$key]),
            };

            // Custom code is rendered raw on the website, so it is never part of the public settings payload.
            $this->settings->set("{$group}.{$key}", $value, public: $group !== 'code');
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    private static function patternRule(array $field): array
    {
        return isset($field['pattern']) && is_string($field['pattern']) ? ['regex:'.$field['pattern']] : [];
    }

    /** @return array<string, list<mixed>> */
    public static function rules(string $group): array
    {
        $rules = [];

        foreach (self::groups()[$group]['fields'] as $key => $field) {
            $base = ($field['required'] ?? false) ? ['required'] : ['nullable'];

            $rules[$key] = match ($field['type']) {
                'text', 'textarea' => [...$base, 'string', 'max:'.($field['max'] ?? 255), ...self::patternRule($field)],
                'code' => ['nullable', 'string', 'max:'.($field['max'] ?? 20000)],
                'email' => [...$base, 'email:rfc', 'max:255'],
                'url' => [...$base, 'url:https,http', 'max:255'],
                'toggle' => ['boolean'],
                'color' => [...$base, 'regex:/^#[0-9a-fA-F]{6}$/'],
                'select' => [...$base, Rule::in(array_map('strval', array_keys($field['options'])))],
                'image' => ['nullable', 'integer', 'exists:media,id'],
                'images' => ['array', 'max:'.($field['max'] ?? 10)],
                'link' => ['nullable', 'array'],
                default => ['nullable'],
            };

            if ($field['type'] === 'images') {
                $rules["{$key}.*"] = ['integer', 'exists:media,id'];
            }

            if ($field['type'] === 'link') {
                $rules["{$key}.label"] = ['nullable', 'string', 'max:40'];
                $rules["{$key}.url"] = ['nullable', 'string', 'max:255', fn ($a, $v, $fail) => SectionSanitizer::safeUrl(trim((string) $v)) || $fail(__('Use a site path like /contact, or a full https:// address.'))];
            }
        }

        return $rules;
    }
}
