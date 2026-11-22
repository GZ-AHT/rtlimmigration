<?php

namespace App\Support\Email;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Makes the HTML of a received email safe to show in the dashboard.
 * Scripts, styles, forms, event handlers, iframes and javascript: links are
 * removed. Remote images are blocked too (they report that the email was
 * opened); pictures embedded in the email are shown through our own route.
 */
class EmailHtml
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->allowRelativeLinks(false)
                ->allowMediaSchemes([])
                ->allowRelativeMedias(true)
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->forceAttribute('a', 'target', '_blank')
                ->withMaxInputLength(1_500_000),
        );
    }

    /** @param list<string|null> $contentIds Content-IDs of the email's attachments, by position. */
    public function clean(string $html, array $contentIds = []): string
    {
        // Embedded pictures (cid:…) become a relative placeholder the page later points at the download route.
        foreach ($contentIds as $index => $cid) {
            if ($cid !== null && $cid !== '') {
                $html = preg_replace('/cid:'.preg_quote($cid, '/').'/i', "cid-{$index}", $html) ?? $html;
            }
        }

        return $this->sanitizer->sanitize($html);
    }

    /** Points the placeholders at the real attachment URLs (done when the message is shown). */
    public static function resolveInline(string $html, callable $urlFor): string
    {
        return preg_replace_callback('/src="cid-(\d+)"/', fn (array $m) => 'src="'.e($urlFor((int) $m[1])).'"', $html) ?? $html;
    }

    public static function toPreview(?string $text, ?string $html): string
    {
        $source = filled($text) ? $text : html_entity_decode(strip_tags((string) preg_replace('/<(style|script)\b.*?<\/\1>/is', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $source)), 0, 200);
    }
}
