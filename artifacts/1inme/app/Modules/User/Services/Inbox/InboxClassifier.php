<?php

namespace App\Modules\User\Services\Inbox;

/**
 * Lightweight rule-based triage classifier.
 *
 * Buckets each new thread into {Lead, Fan, Sponsorship, Support, Spam}
 * with a confidence score. Designed to plug into the existing AI feature
 * framework later — `classify()` returns the same shape any AI backend
 * would, so swapping it for an AI call is a one-liner.
 */
class InboxClassifier
{
    /**
     * @return array{category:string, confidence:float, reason:?string}
     */
    public function classify(string $body, ?string $subject = null, ?string $channel = null, bool $isSpamFlagged = false): array
    {
        if ($isSpamFlagged) {
            return ['category' => 'spam', 'confidence' => 0.95, 'reason' => 'flagged_by_spam_filter'];
        }

        $haystack = mb_strtolower(trim(($subject ?? '') . "\n" . $body));

        $rules = [
            'sponsorship' => [
                'collab', 'collaboration', 'partnership', 'partner',
                'sponsor', 'sponsored', 'brand deal', 'press kit',
                'media kit', 'rate card', 'paid post', 'gifted',
                'pr package', 'campaign', 'ambassador',
            ],
            'support' => [
                'help', 'broken', 'not working', 'bug', 'error', 'issue',
                'refund', 'cancel', 'unsubscribe', 'problem', 'support',
                'doesn\'t work', 'didn\'t work', 'glitch',
            ],
            'lead' => [
                'quote', 'pricing', 'price', 'rate', 'how much', 'cost',
                'hire', 'book', 'booking', 'available', 'availability',
                'project', 'enquiry', 'inquiry', 'interested in', 'work together',
            ],
            'fan' => [
                'love your', 'amazing', 'huge fan', 'big fan', 'inspiring',
                'thank you', 'thanks for', 'great content', 'made my day',
                'obsessed', 'congrats', '<3', '❤', '🙌',
            ],
        ];

        $best = ['category' => 'lead', 'confidence' => 0.35, 'reason' => 'default'];
        foreach ($rules as $cat => $keywords) {
            $hits = 0;
            $matched = null;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $hits++;
                    $matched = $matched ?? $kw;
                }
            }
            if ($hits > 0) {
                $score = min(0.95, 0.55 + ($hits * 0.1));
                if ($score > $best['confidence']) {
                    $best = ['category' => $cat, 'confidence' => $score, 'reason' => "keyword:{$matched}"];
                }
            }
        }

        // Sponsorship channel hint: emails from typical brand domains.
        if ($best['category'] !== 'sponsorship' && preg_match('/@(\w+)\.(com|co|io)/', $haystack, $m)) {
            $brandy = ['nike', 'adidas', 'spotify', 'shopify', 'hellofresh', 'glossier'];
            if (in_array(strtolower($m[1] ?? ''), $brandy, true)) {
                $best = ['category' => 'sponsorship', 'confidence' => 0.7, 'reason' => 'brand_domain'];
            }
        }

        return $best;
    }

    /**
     * Public so the controller can persist a manual override and we treat
     * it as authoritative training feedback for future model swaps.
     */
    public function manualOverride(string $category): array
    {
        return ['category' => $category, 'confidence' => 1.0, 'reason' => 'manual_override'];
    }
}
