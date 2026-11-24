<?php

namespace App\Support\Chat\AutoReply;

/** Whole-word and whole-phrase keyword matching for chat messages (Bangla included). */
class KeywordMatcher
{
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    /** 0 when the keyword is not in the text, else more for longer phrases. `$text` must be normalized. */
    public static function score(string $text, string $keyword): int
    {
        $keyword = self::normalize($keyword);

        if ($keyword === '') {
            return 0;
        }

        // Letters, digits and combining marks (Bangla vowel signs) count as part of a word.
        $pattern = '/(?<![\p{L}\p{N}\p{M}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}\p{M}])/u';

        return preg_match($pattern, $text) === 1 ? 2 * (substr_count($keyword, ' ') + 1) : 0;
    }

    /** @param  list<string>  $keywords */
    public static function matchesAny(string $message, array $keywords): bool
    {
        $text = self::normalize($message);

        foreach ($keywords as $keyword) {
            if (self::score($text, $keyword) > 0) {
                return true;
            }
        }

        return false;
    }
}
