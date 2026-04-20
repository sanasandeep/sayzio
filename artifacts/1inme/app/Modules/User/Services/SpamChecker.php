<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\User;
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
 *
 * Per-user customization (read from User.settings['spam']):
 *  - blocked_keywords:           extra keywords to add to the default list
 *  - disabled_default_keywords:  default keywords the user opted out of
 *  - trusted_emails / trusted_phones: senders whose payloads always pass
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
     *  - user_id:  ?int     (account owning the inbox; enables per-user tuning)
     *  - email:    ?string  (sender email, for the trusted-senders bypass)
     *  - phone:    ?string  (sender phone, for the trusted-senders bypass)
     */
    public function check(array $payload): array
    {
        $honeypot = trim((string) ($payload['honeypot'] ?? ''));
        if ($honeypot !== '') {
            return ['is_spam' => true, 'reason' => 'honeypot'];
        }

        $userSpam = $this->loadUserSpamSettings($payload['user_id'] ?? null);

        // Trusted-sender bypass: if the sender's email or phone is on the
        // creator's allowlist, never flag the payload as spam.
        $email = $this->normalizeEmail($payload['email'] ?? null);
        $phone = $this->normalizePhone($payload['phone'] ?? null);
        if ($email !== null && in_array($email, $userSpam['trusted_emails'], true)) {
            return ['is_spam' => false, 'reason' => null];
        }
        if ($phone !== null && in_array($phone, $userSpam['trusted_phones'], true)) {
            return ['is_spam' => false, 'reason' => null];
        }

        $text = (string) ($payload['text'] ?? '');

        if ($this->countLinks($text) >= self::LINK_THRESHOLD) {
            return ['is_spam' => true, 'reason' => 'too_many_links'];
        }

        $keywords = $this->effectiveKeywords($userSpam);
        if ($keyword = $this->matchedKeyword($text, $keywords)) {
            return ['is_spam' => true, 'reason' => 'blocked_keyword:' . $keyword];
        }

        $ip    = trim((string) ($payload['ip'] ?? ''));
        $scope = trim((string) ($payload['scope'] ?? 'global'));
        if ($ip !== '' && $this->exceedsRateLimit($ip, $scope)) {
            return ['is_spam' => true, 'reason' => 'rate_limit'];
        }

        return ['is_spam' => false, 'reason' => null];
    }

    /**
     * Build the effective blocked-keyword list for a user: defaults minus
     * anything the user disabled, plus any extra keywords they added.
     */
    public function effectiveKeywords(array $userSpam): array
    {
        $disabled = array_map('mb_strtolower', $userSpam['disabled_default_keywords']);
        $defaults = array_values(array_filter(
            self::BLOCKED_KEYWORDS,
            fn($kw) => !in_array(mb_strtolower($kw), $disabled, true)
        ));
        $extra = array_values(array_filter(array_map(
            fn($kw) => trim((string) $kw),
            $userSpam['blocked_keywords']
        ), fn($kw) => $kw !== ''));

        // Dedupe case-insensitively while preserving the user's preferred casing.
        $seen = [];
        $out = [];
        foreach (array_merge($defaults, $extra) as $kw) {
            $k = mb_strtolower($kw);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = $kw;
        }
        return $out;
    }

    /**
     * Normalize and load the user's spam settings, always returning the
     * canonical shape (so callers don't have to null-check each key).
     */
    public function loadUserSpamSettings($userId): array
    {
        $blank = [
            'blocked_keywords' => [],
            'disabled_default_keywords' => [],
            'trusted_emails' => [],
            'trusted_phones' => [],
        ];
        if (!$userId) return $blank;

        $user = User::find($userId);
        if (!$user) return $blank;

        $raw = ($user->settings ?? [])['spam'] ?? [];
        return [
            'blocked_keywords'          => $this->cleanList($raw['blocked_keywords'] ?? [], 'mb_strtolower'),
            'disabled_default_keywords' => $this->cleanList($raw['disabled_default_keywords'] ?? [], 'mb_strtolower'),
            'trusted_emails'            => $this->cleanList($raw['trusted_emails'] ?? [], [$this, 'normalizeEmail']),
            'trusted_phones'            => $this->cleanList($raw['trusted_phones'] ?? [], [$this, 'normalizePhone']),
        ];
    }

    protected function cleanList($list, $normalizer): array
    {
        if (!is_array($list)) return [];
        $out = [];
        foreach ($list as $item) {
            if (!is_string($item) && !is_numeric($item)) continue;
            $v = call_user_func($normalizer, (string) $item);
            if ($v === null || $v === '') continue;
            $out[] = $v;
        }
        return array_values(array_unique($out));
    }

    public function normalizeEmail($value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') return null;
        return mb_strtolower($v);
    }

    public function normalizePhone($value): ?string
    {
        $v = preg_replace('/[^\d+]/', '', (string) ($value ?? ''));
        return ($v === '' || $v === null) ? null : $v;
    }

    protected function countLinks(string $text): int
    {
        if ($text === '') return 0;
        // Catches http(s)://… and bare-domain "example.com/path" style links.
        $count  = preg_match_all('#https?://[^\s<>"\']+#i', $text);
        $count += preg_match_all('#(?<![\w@./])(?:www\.)?[a-z0-9-]+\.(?:com|net|org|io|co|ru|cn|info|xyz|top|biz|click|link)\b#i', $text);
        return (int) $count;
    }

    protected function matchedKeyword(string $text, array $keywords): ?string
    {
        if ($text === '') return null;
        $haystack = mb_strtolower($text);
        foreach ($keywords as $kw) {
            $needle = mb_strtolower((string) $kw);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return $kw;
            }
        }
        return null;
    }

    /**
     * Parse a stored reason string into ['code' => string, 'detail' => ?string].
     * Reason codes use 'code' or 'code:detail' shape (e.g. 'too_many_links',
     * 'blocked_keyword:viagra'). Unknown / empty reasons return null.
     */
    public static function parseReason(?string $reason): ?array
    {
        if (!is_string($reason) || $reason === '') return null;
        [$code, $detail] = array_pad(explode(':', $reason, 2), 2, null);
        return ['code' => $code, 'detail' => $detail];
    }

    /**
     * Render a stored reason as a short human-readable badge label, e.g.
     * "Blocked: viagra", "Too many links", "Honeypot", "Rate limit".
     */
    public static function reasonLabel(?string $reason): ?string
    {
        $p = self::parseReason($reason);
        if (!$p) return null;
        return match ($p['code']) {
            'blocked_keyword' => 'Blocked: ' . ($p['detail'] ?: 'keyword'),
            'too_many_links'  => 'Too many links',
            'rate_limit'      => 'Rate limit',
            'honeypot'        => 'Honeypot',
            default           => ucfirst(str_replace('_', ' ', (string) $p['code'])),
        };
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
