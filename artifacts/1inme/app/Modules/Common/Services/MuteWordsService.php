<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\User;

/**
 * Per-creator mute words (Task #1211). Used by CreatorPostComment
 * creation, the inbox DM intake, and the report notifier so a
 * creator can silently auto-hide replies that contain slurs they
 * don't want to see in their notifications. Matching is case- and
 * word-boundary insensitive — "k!ll yourself", "kill u", "killyou"
 * all match `kill`.
 */
class MuteWordsService
{
    /** Cap how many words we let a creator add — keep the regex bounded. */
    public const MAX_WORDS = 200;

    public function wordsFor(User $creator): array
    {
        $raw = is_array($creator->mute_words ?? null) ? $creator->mute_words : [];
        $out = [];
        foreach ($raw as $w) {
            $w = mb_strtolower(trim((string) $w));
            if ($w === '') continue;
            $out[] = $w;
            if (count($out) >= self::MAX_WORDS) break;
        }
        return $out;
    }

    /**
     * Returns the first muted word matched in $body, or null when no
     * words match. Strips non-alphanumerics from the haystack so common
     * obfuscations ("k!ll") still match.
     */
    public function firstMatch(User $creator, string $body): ?string
    {
        $words = $this->wordsFor($creator);
        if (empty($words)) return null;
        $needle = mb_strtolower($body);
        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', '', $needle);

        foreach ($words as $w) {
            $wStripped = preg_replace('/[^\p{L}\p{N}]+/u', '', $w);
            if ($wStripped === '') continue;
            if (mb_strpos($needle,   $w)         !== false) return $w;
            if (mb_strpos($stripped, $wStripped) !== false) return $w;
        }
        return null;
    }

    public function matches(User $creator, string $body): bool
    {
        return $this->firstMatch($creator, $body) !== null;
    }

    /** Normalize free-text input into a clean word list. */
    public function normaliseInput(string|array $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,;]+/', $raw) ?: [];
        }
        $out = [];
        foreach ((array) $raw as $w) {
            $w = mb_strtolower(trim((string) $w));
            if ($w !== '' && mb_strlen($w) <= 64) $out[] = $w;
        }
        return array_values(array_unique($out));
    }
}
