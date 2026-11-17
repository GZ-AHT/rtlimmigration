<?php

namespace App\Support\Chat\Security;

use App\Enums\ModerationStatus;
use App\Support\Chat\ChatSecuritySettings;

/**
 * Checks the text of a student's or visitor's message.
 *
 * - Script or HTML injection, blocked keywords and blocked domains:
 *   Blocked (held, kept for review).
 * - Two or more warning signs together (e.g. a scam phrase plus a
 *   suspicious link), or a wall of links: Pending (held for review).
 * - One warning sign: Flagged. The message is delivered and a moderator
 *   is asked to look at it.
 *
 * An ordinary link or an unfamiliar word on its own changes nothing:
 * students send university and embassy links all the time.
 */
class ContentInspector
{
    /** Reason codes and how they read for moderators. */
    public const REASONS = [
        'script_injection' => 'HTML or script code',
        'blocked_keyword' => 'Blocked keyword',
        'blocked_domain' => 'Link to a blocked domain',
        'suspicious_link' => 'Suspicious link',
        'too_many_links' => 'Too many links',
        'phishing' => 'Possible phishing',
        'scam' => 'Possible scam',
        'promotional' => 'Promotional content',
        'repeated_characters' => 'Excessive repeated characters',
        'shouting' => 'Written in capitals',
        'repeated_message' => 'Same message sent repeatedly',
    ];

    private const SCRIPT_PATTERNS = [
        '~<\s*/?\s*script\b~i',
        '~<\s*(iframe|object|embed|applet|meta|base|form|svg|math|link|style|frameset|frame)\b~i',
        '~<[^>]*\s(on[a-z]+|formaction|srcdoc)\s*=~i',
        '~(java|vb)script\s*:~i',
        '~data\s*:\s*(text/html|application/(x-)?javascript|image/svg\+xml)~i',
        '~expression\s*\(~i',
        '~<\?(php|=)~i',
        '~\$\{\s*jndi\s*:~i',
    ];

    private const PHISHING = [
        'verify your account', 'confirm your password', 'enter your password', 'send me your password', 'send your password',
        'share your otp', 'send the otp', 'send me the otp', 'your otp', 'login details', 'login credentials',
        'account will be suspended', 'account has been suspended', 'account will be closed', 'urgent action required',
        'update your payment details', 'confirm your bank details', 'send your card details', 'card number and cvv',
    ];

    private const SCAM = [
        'bitcoin', 'crypto investment', 'usdt', 'investment opportunity', 'double your money', 'guaranteed returns', 'guaranteed profit',
        'western union', 'moneygram', 'gift card', 'wire the money', 'you have won', 'you have been selected', 'lottery', 'inheritance',
        'claim your prize', '100% visa', 'visa without documents', 'fake passport', 'fake documents', 'fake certificate', 'buy ielts', 'ielts without exam',
    ];

    private const PROMOTIONAL = [
        'buy now', 'limited time offer', 'special offer', 'discount code', 'promo code', 'order now', 'subscribe now',
        'earn money', 'make money online', 'work from home', 'seo services', 'web design services', 'backlinks', 'guest post',
        'casino', 'betting tips', 'viagra', 'cheap loans', 'payday loan', 'forex signals', 'followers for sale',
    ];

    private const SHORTENERS = [
        'bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'is.gd', 'ow.ly', 'cutt.ly', 'rb.gy', 'shorturl.at', 'tiny.cc',
        'buff.ly', 'rebrand.ly', 'bl.ink', 'shorte.st', 'adf.ly', 'v.gd', 'qr.ae', 'lnkd.in', 'trib.al', 'urlz.fr',
    ];

    private const RISKY_TLDS = ['zip', 'mov', 'top', 'click', 'gq', 'tk', 'ml', 'cf', 'ga', 'work', 'loan', 'men', 'kim', 'rest', 'cam', 'country', 'xin', 'support'];

    private const RISKY_FILE_EXTENSIONS = ['exe', 'scr', 'bat', 'cmd', 'com', 'msi', 'apk', 'jar', 'vbs', 'ps1', 'hta', 'dll', 'iso', 'img', 'lnk', 'js', 'wsf'];

    private const MAX_PLAIN_LINKS = 5;

    public function __construct(private ChatSecuritySettings $settings) {}

    public function inspect(string $body): Verdict
    {
        $verdict = Verdict::approved();
        $text = self::normalise($body);

        foreach (self::SCRIPT_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $verdict = $verdict->with(ModerationStatus::Blocked, 'script_injection');
                break;
            }
        }

        foreach ($this->settings->blockedKeywords() as $keyword) {
            if (self::containsPhrase($text, $keyword)) {
                $verdict = $verdict->with(ModerationStatus::Blocked, 'blocked_keyword');
                break;
            }
        }

        // Domains are checked in the whole text, so "example.com" without
        // https:// still counts.
        foreach ($this->settings->blockedDomains() as $domain) {
            if (preg_match('~(?<![a-z0-9.-])(?:[a-z0-9-]+\.)*'.preg_quote($domain, '~').'(?![a-z0-9-])~i', $text) === 1) {
                $verdict = $verdict->with(ModerationStatus::Blocked, 'blocked_domain');
                break;
            }
        }

        $urls = self::extractUrls($text);
        $suspicious = [];

        foreach ($urls as $url) {
            if ($this->linkIsSuspicious($url)) {
                $suspicious[] = mb_substr($url, 0, 300);
            }
        }

        $signs = 0;

        if ($suspicious !== []) {
            $verdict = new Verdict($verdict->status, $verdict->reasons, $suspicious);
            $verdict = $verdict->with(ModerationStatus::Flagged, 'suspicious_link');
            $signs++;
        }

        if (count($urls) > self::MAX_PLAIN_LINKS) {
            $verdict = $verdict->with(ModerationStatus::Pending, 'too_many_links');
        }

        foreach (['phishing' => self::PHISHING, 'scam' => self::SCAM, 'promotional' => self::PROMOTIONAL] as $reason => $phrases) {
            foreach ($phrases as $phrase) {
                if (self::containsPhrase($text, $phrase)) {
                    $verdict = $verdict->with(ModerationStatus::Flagged, $reason);
                    $signs++;
                    break;
                }
            }
        }

        // Promotional text with any link at all is the usual shape of spam.
        if (in_array('promotional', $verdict->reasons, true) && $urls !== [] && ! in_array('suspicious_link', $verdict->reasons, true)) {
            $signs++;
        }

        if ($signs >= 2) {
            $verdict = $verdict->with(ModerationStatus::Pending, $verdict->reasons[0]);
        }

        if (preg_match('~(\S)\1{19,}~u', $text) === 1) {
            $verdict = $verdict->with(ModerationStatus::Flagged, 'repeated_characters');
        }

        $letters = (string) preg_replace('~[^\p{L}]~u', '', $body);

        if (mb_strlen($letters) >= 40 && mb_strlen((string) preg_replace('~[^\p{Lu}]~u', '', $letters)) / mb_strlen($letters) >= 0.85) {
            $verdict = $verdict->with(ModerationStatus::Flagged, 'shouting');
        }

        return $verdict;
    }

    /**
     * Lower-case, with invisible characters removed and look-alike
     * characters folded, so "v\u{200B}iagra" or full-width letters do not
     * slip past the checks. Used for matching only; the message is stored
     * as written.
     */
    public static function normalise(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_KC);
        }

        $text = (string) preg_replace('~[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}\x{00AD}]~u', '', $text);

        return mb_strtolower($text);
    }

    /** @return list<string> */
    public static function extractUrls(string $text): array
    {
        preg_match_all('~\b(?:https?://|www\.)[^\s<>"\'`]+~i', $text, $m);

        $urls = $m[0];

        // Shortened links are often pasted without https://.
        preg_match_all('~(?<![a-z0-9.@-])(?:'.implode('|', array_map(fn ($s) => preg_quote($s, '~'), self::SHORTENERS)).')/[^\s<>"\'`]+~i', $text, $short);

        foreach ($short[0] as $s) {
            $urls[] = 'http://'.$s;
        }

        return array_values(array_unique(array_map(fn (string $u) => rtrim($u, '.,;:!?)]}'), $urls)));
    }

    private function linkIsSuspicious(string $url): bool
    {
        $parts = parse_url(str_starts_with(strtolower($url), 'www.') ? "http://{$url}" : $url);

        if ($parts === false || ! isset($parts['host'])) {
            return true;
        }

        $host = rtrim(strtolower($parts['host']), '.');

        foreach ($this->settings->trustedDomains() as $trusted) {
            if ($host === $trusted || str_ends_with($host, ".{$trusted}")) {
                return false;
            }
        }

        $path = strtolower($parts['path'] ?? '');
        $tld = substr($host, (int) strrpos($host, '.') + 1);

        return isset($parts['user'])
            || isset($parts['port'])
            || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false
            || str_contains($host, 'xn--')
            || in_array($host, self::SHORTENERS, true)
            || in_array($tld, self::RISKY_TLDS, true)
            || substr_count($host, '.') > 4
            || strlen($url) > 300
            || preg_match('~\.('.implode('|', self::RISKY_FILE_EXTENSIONS).')$~', $path) === 1;
    }

    private static function containsPhrase(string $normalisedText, string $phrase): bool
    {
        $phrase = self::normalise($phrase);

        return $phrase !== '' && preg_match('~(?<![\p{L}\p{N}])'.preg_quote($phrase, '~').'(?![\p{L}\p{N}])~u', $normalisedText) === 1;
    }
}
