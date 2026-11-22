<?php

namespace App\Support\Cms;

use App\Models\AuthorProfile;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Testimonial;
use App\Models\University;
use App\Support\Settings\SettingsRepository;

/**
 * Where each library file is used (REQ §23.4 media "usage tracking"): page
 * sections, blog posts and authors, universities, testimonials, search/share
 * images and the logos and images in Site settings. Used to show "Used in"
 * on the library page and to stop an in-use file being deleted by accident.
 */
class MediaUsage
{
    /**
     * @param  list<int>  $ids  files to look up
     * @return array<int, list<string>> file id => short labels such as "Page: Home"
     */
    public static function for(array $ids): array
    {
        $wanted = array_fill_keys($ids, true);
        $found = [];
        $add = function (mixed $id, string $label) use ($wanted, &$found): void {
            if (is_numeric($id) && isset($wanted[(int) $id])) {
                $found[(int) $id][$label] = true;
            }
        };

        if ($wanted === []) {
            return [];
        }

        foreach (Page::query()->select(['id', 'title', 'sections'])->get() as $page) {
            foreach (SectionTypes::mediaIds($page->sections) as $id) {
                $add($id, 'Page: '.$page->title);
            }
        }

        foreach (BlogPost::query()->whereIn('featured_image_id', $ids)->get(['id', 'title', 'featured_image_id']) as $post) {
            $add($post->featured_image_id, 'Blog post: '.$post->title);
        }

        foreach (AuthorProfile::query()->whereIn('avatar_id', $ids)->get(['id', 'display_name', 'avatar_id']) as $author) {
            $add($author->avatar_id, 'Author: '.$author->display_name);
        }

        foreach (Testimonial::query()->whereIn('photo_id', $ids)->get(['id', 'name', 'photo_id']) as $testimonial) {
            $add($testimonial->photo_id, 'Testimonial: '.$testimonial->name);
        }

        foreach (University::query()->get(['id', 'name', 'logo_id', 'banner_id', 'gallery']) as $university) {
            foreach ([$university->logo_id, $university->banner_id, ...($university->gallery ?? [])] as $id) {
                $add($id, 'University: '.$university->name);
            }
        }

        foreach (SeoMeta::query()->whereIn('og_image_id', $ids)->get(['id', 'seoable_type', 'og_image_id']) as $seo) {
            $add($seo->og_image_id, 'Share image: '.class_basename((string) $seo->seoable_type));
        }

        $settings = app(SettingsRepository::class);

        foreach (SiteSettings::groups() as $group => $def) {
            foreach ($def['fields'] as $key => $field) {
                if (! in_array($field['type'], ['image', 'images'], true)) {
                    continue;
                }

                $value = $settings->get("{$group}.{$key}");

                foreach (is_array($value) ? $value : [$value] as $id) {
                    $add($id, 'Site setting: '.$field['label']);
                }
            }
        }

        return array_map(fn (array $labels) => array_keys($labels), $found);
    }
}
