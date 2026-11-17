<?php

namespace App\Support\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Cleans HTML from the rich text editor before it is stored (REQ §31.5).
 * Only the formatting the editor produces survives: no scripts, styles,
 * event handlers, iframes or javascript: links. Links to other sites open
 * safely; images must be http(s).
 */
class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::sanitizer()->sanitize($html);
    }

    /** Plain text for excerpts and search, from stored HTML. */
    public static function text(?string $html, int $limit = 0): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>', '</h2>', '</h3>'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5)));

        return $limit > 0 ? (string) str($text)->limit($limit) : $text;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('b')
                ->allowElement('em')
                ->allowElement('i')
                ->allowElement('u')
                ->allowElement('s')
                ->allowElement('code')
                ->allowElement('pre')
                ->allowElement('blockquote')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('h4')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('hr')
                ->allowElement('a', ['href', 'title', 'target', 'rel'])
                ->allowElement('img', ['src', 'alt', 'width', 'height'])
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowMediaSchemes(['https', 'http'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(200_000)
        );
    }
}
