<?php

namespace App\Support\Chat\AutoReply;

use App\Models\ChatConversation;

/**
 * Picks the active reply whose keywords fit the message best. A keyword
 * counts only as a whole word or phrase, and longer phrases outweigh single
 * words, so "what are your services" beats a bare "hello" in the same message.
 */
class KeywordReplyProvider implements ReplyProvider
{
    public function __construct(private AutoReplySettings $settings) {}

    public function reply(ChatConversation $conversation, string $message): ?string
    {
        $text = self::normalize($message);
        $best = null;
        $bestScore = 0;

        foreach ($this->settings->rules() as $rule) {
            if (! $rule['active'] || trim($rule['reply']) === '') {
                continue;
            }

            $score = 0;

            foreach ($rule['keywords'] as $keyword) {
                $score += self::score($text, self::normalize($keyword));
            }

            // Strictly greater: on a tie the rule listed first wins.
            if ($score > $bestScore) {
                $best = $rule['reply'];
                $bestScore = $score;
            }
        }

        return $best;
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    private static function score(string $text, string $keyword): int
    {
        if ($keyword === '') {
            return 0;
        }

        // Letters, digits and combining marks (Bangla vowel signs) count as part of a word.
        $pattern = '/(?<![\p{L}\p{N}\p{M}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}\p{M}])/u';

        return preg_match($pattern, $text) === 1 ? 2 * (substr_count($keyword, ' ') + 1) : 0;
    }
}
