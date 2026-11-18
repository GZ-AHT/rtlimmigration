<?php

namespace App\Support\Cms;

use App\Models\Media;
use App\Support\Content\RichText;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cleans a page's sections against SectionTypes before they are stored:
 * unknown types and fields are dropped, text is length-limited and plain,
 * rich text is sanitised, links must be safe, images must exist.
 */
class SectionSanitizer
{
    public const MAX_SECTIONS = 40;

    /** @var array<int, string> media id => 'image' or 'video' */
    private array $knownMedia = [];

    /**
     * @param  array<int, mixed>  $sections
     * @return list<array{id: string, type: string, enabled: bool, data: array<string, mixed>, settings: array<string, mixed>}>
     */
    public function clean(array $sections): array
    {
        if (count($sections) > self::MAX_SECTIONS) {
            throw ValidationException::withMessages(['sections' => __('A page can have at most :max sections.', ['max' => self::MAX_SECTIONS])]);
        }

        $this->knownMedia = Media::query()->whereKey(SectionTypes::mediaIds($sections))->pluck('mime_type', 'id')
            ->map(fn (string $mime) => str_starts_with($mime, 'video/') ? 'video' : 'image')->all();
        $clean = [];

        foreach (array_values($sections) as $section) {
            if (! is_array($section) || ! is_string($section['type'] ?? null) || SectionTypes::get($section['type']) === null) {
                continue;
            }

            $type = $section['type'];
            $id = is_string($section['id'] ?? null) && preg_match('/^[A-Za-z0-9-]{6,40}$/', $section['id']) ? $section['id'] : (string) Str::uuid();
            $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];

            $clean[] = [
                'id' => $id,
                'type' => $type,
                'enabled' => (bool) ($section['enabled'] ?? true),
                'data' => $this->fields(SectionTypes::get($type)['fields'], is_array($section['data'] ?? null) ? $section['data'] : []),
                'settings' => [
                    'background' => array_key_exists((string) ($settings['background'] ?? ''), SectionTypes::BACKGROUNDS) ? (string) $settings['background'] : 'white',
                    'spacing' => array_key_exists((string) ($settings['spacing'] ?? ''), SectionTypes::SPACING) ? (string) $settings['spacing'] : 'default',
                    'anchor' => Str::limit(Str::slug((string) ($settings['anchor'] ?? '')), 40, ''),
                    'hide_on_mobile' => (bool) ($settings['hide_on_mobile'] ?? false),
                ],
            ];
        }

        return $clean;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $fields, array $data): array
    {
        $out = [];

        foreach ($fields as $key => $field) {
            $value = $data[$key] ?? null;

            $out[$key] = match ($field['type']) {
                'text', 'textarea' => Str::limit(trim(strip_tags((string) (is_scalar($value) ? $value : ''))), (int) ($field['max'] ?? 255), ''),
                'richtext' => RichText::clean(is_string($value) ? $value : ''),
                'toggle' => (bool) ($value ?? $field['default'] ?? false),
                'number' => max((int) ($field['min'] ?? 0), min((int) ($field['max'] ?? 1000), (int) (is_numeric($value) ? $value : ($field['default'] ?? 0)))),
                'select' => is_string($value) && array_key_exists($value, $field['options']) ? $value : (string) ($field['default'] ?? array_key_first($field['options'])),
                // An image field only takes an image, a video field only a video.
                'image', 'video' => is_numeric($value) && ($this->knownMedia[(int) $value] ?? null) === $field['type'] ? (int) $value : null,
                'link' => $this->link(is_array($value) ? $value : []),
                'repeater' => array_values(array_map(
                    fn (array $item) => $this->fields($field['fields'], $item),
                    array_slice(array_filter(is_array($value) ? $value : [], 'is_array'), 0, (int) ($field['max'] ?? 12)),
                )),
                default => null,
            };
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{label: string, url: string}
     */
    private function link(array $value): array
    {
        $url = trim((string) ($value['url'] ?? ''));

        return [
            'label' => Str::limit(trim(strip_tags((string) ($value['label'] ?? ''))), 60, ''),
            'url' => self::safeUrl($url) ? Str::limit($url, 255, '') : '',
        ];
    }

    /** Site-relative paths, #anchors, http(s), mailto and tel only: never javascript: or data:. */
    public static function safeUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }

        return (bool) preg_match('~^(/(?!/)|#|https?://|mailto:|tel:)~i', $url) && ! preg_match('/[\s<>"\']/', $url);
    }
}
