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

    public function needsHuman(ChatConversation $conversation, string $message): bool
    {
        return KeywordMatcher::matchesAny($message, $this->settings->handoverKeywords());
    }

    public function reply(ChatConversation $conversation, string $message): ?string
    {
        $text = KeywordMatcher::normalize($message);
        $best = null;
        $bestScore = 0;

        foreach ($this->settings->rules() as $rule) {
            if (! $rule['active'] || trim($rule['reply']) === '') {
                continue;
            }

            $score = 0;

            foreach ($rule['keywords'] as $keyword) {
                $score += KeywordMatcher::score($text, $keyword);
            }

            // Strictly greater: on a tie the rule listed first wins.
            if ($score > $bestScore) {
                $best = $rule['reply'];
                $bestScore = $score;
            }
        }

        return $best;
    }
}
