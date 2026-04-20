<?php

namespace App\Modules\User\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight, dependency-free spam heuristics for public inbound submissions
 * (form submissions and biolink subscribers). Matches are stored with
 * is_spam=true so creators can review them in the dedicated Spam tab.
 *
 * Heuristics (any single match flags the item as spam):
 *  - Honeypot field filled in (bots autofill every input)
 *  - Per-IP rate limit on a sliding 60s window
 *  - Excess link count in free-text fields (>= LINK_THRESHOLD)
 *  - Blocked keyword match (case-insensitive substring)
 */
class SpamChecker
{
    public const LINK_THRESHOLD = 3;
    public const RATE_LIMIT_PER_MINUTE = 6;

    /**
     * Default blocked keywords. Kept intentionally short so it catches the
     * common spam vectors without false-positiving real submissions.
     */
    public const BLOCKED_KEYWORDS = [
        'viagra', 'cialis', 'casino', 'poker', 'porn', 'xxx',
        'crypto giveaway', 'bitcoin doubler', 'free btc', 'forex signals',
        'cheap rolex', 'replica watches', 'seo services',
        'click here to win', 'work from home',
    ];

    /**
     * Evaluate a payload. Returns ['is_spam' => bool, 'reason' => ?string].
     *
     * Expected $payload keys:
     *  - honeypot: ?string  (raw value of the hidden trap field)
     *  - ip:       ?string
     *  - text:     ?string  (concatenation of free-text fields to scan)
     *  - scope:    ?string  (rate-limit bucket; e.g. 'form:1' or 'subscribe')
     */
    public function check(array $payload): array
    {
        $honeypot = trim((string) ($payload['honeypot'] ?? ''));
        if ($honeypot !== '') {
            return ['is_spam' => true, 'reason' => 'honeypot'];
        }

        $text = (string) ($payload['text'] ?? '');

        if ($this->countLinks($text) >= self::LINK_THRESHOLD) {
            return ['is_spam' => true, 'reason' => 'too_many_links'];
        }

        if ($keyword = $this->matchedKeyword($text)) {
            return ['is_spam' => true, 'reason' => 'blocked_keyword:' . $keyword];
        }

        $ip    = trim((string) ($payload['ip'] ?? ''));
        $scope = trim((string) ($payload['scope'] ?? 'global'));
        if ($ip !== '' && $this->exceedsRateLimit($ip, $scope)) {
            return ['is_spam' => true, 'reason' => 'rate_limit'];
        }

        return ['is_spam' => false, 'reason' => null];
    }

    protected function countLinks(string $text): int
    {
        if ($text === '') return 0;
        // Catches http(s)://… and bare-domain "example.com/path" style links.
        $count  = preg_match_all('#https?://[^\s<>"\']+#i', $text);
        $count += preg_match_all('#(?<![\w@./])(?:www\.)?[a-z0-9-]+\.(?:com|net|org|io|co|ru|cn|info|xyz|top|biz|click|link)\b#i', $text);
        return (int) $count;
    }

    protected function matchedKeyword(string $text): ?string
    {
        if ($text === '') return null;
        $haystack = mb_strtolower($text);
        foreach (self::BLOCKED_KEYWORDS as $kw) {
            if ($kw !== '' && str_contains($haystack, $kw)) {
                return $kw;
            }
        }
        return null;
    }

    protected function exceedsRateLimit(string $ip, string $scope): bool
    {
        $key = 'spamcheck:' . $scope . ':' . sha1($ip);
        $count = (int) Cache::get($key, 0);
        $count++;
        // 60s sliding bucket — Laravel's Cache::put with seconds resets the
        // window each write; that's acceptable here because a sustained burst
        // will keep tripping the threshold either way.
        Cache::put($key, $count, 60);
        return $count > self::RATE_LIMIT_PER_MINUTE;
    }
}
