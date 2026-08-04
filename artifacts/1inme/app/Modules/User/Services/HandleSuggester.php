<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Services\BannedNameChecker;
use App\Modules\User\Models\User;
use Illuminate\Support\Str;

/**
 * Generates a small set of available handle suggestions for a user
 * who has been forced to rename (their existing handle was banned).
 * Each suggestion is verified against the banned-names list and
 * checked for uniqueness against the users table so the user can
 * one-click apply any of them with confidence.
 */
class HandleSuggester
{
    private const MAX_SUGGESTIONS = 5;
    private const MAX_CANDIDATES  = 60;

    /**
     * Build up to MAX_SUGGESTIONS available handle candidates derived
     * from the user's display name and (optionally) their current
     * handle. Returns a deduplicated, order-preserving list.
     */
    public static function suggest(User $user): array
    {
        $candidates = self::buildCandidates($user);

        $taken = self::existingHandlesLowered($candidates, $user->id);

        $out  = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            if (count($out) >= self::MAX_SUGGESTIONS) break;

            $clean = self::normalise($candidate);
            if ($clean === '' || strlen($clean) < 3) continue;
            if (isset($seen[$clean])) continue;
            $seen[$clean] = true;

            if (isset($taken[$clean])) continue;
            if (BannedNameChecker::isBanned($clean)) continue;

            $out[] = $clean;
        }

        return $out;
    }

    private static function buildCandidates(User $user): array
    {
        $name        = trim((string) ($user->name ?? ''));
        $handle      = trim((string) ($user->handle ?? ''));
        $nameSlug    = self::normalise($name);
        $handleSlug  = self::normalise($handle);

        $bases = array_values(array_unique(array_filter([
            $nameSlug,
            $handleSlug,
            $nameSlug !== '' ? str_replace(['-', '_'], '', $nameSlug) : '',
            $handleSlug !== '' ? rtrim(preg_replace('/\d+$/', '', $handleSlug), '_-') : '',
        ])));

        if (empty($bases)) {
            $bases = ['user' . $user->id];
        }

        $year       = (int) date('Y');
        $shortYear  = (int) date('y');
        $candidates = [];

        foreach ($bases as $base) {
            if ($base === '') continue;
            $candidates[] = $base . '_' . $shortYear;
            $candidates[] = $base . $year;
            $candidates[] = $base . '.official';
            $candidates[] = $base . '_hq';
            $candidates[] = 'the' . $base;
            $candidates[] = $base . '_real';
            for ($i = 1; $i <= 9; $i++) {
                $candidates[] = $base . $i;
                $candidates[] = $base . '_' . $i;
            }
            for ($i = 0; $i < 12; $i++) {
                $candidates[] = $base . random_int(10, 9999);
            }
        }

        return array_slice($candidates, 0, self::MAX_CANDIDATES);
    }

    /**
     * Apply the same character set the validation rule (and stored
     * handles) use: lowercase letters, digits, underscore, hyphen.
     */
    private static function normalise(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', '_', $value);
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        $value = preg_replace('/[_-]{2,}/', '_', $value);
        $value = trim($value, '_-');
        return (string) Str::limit($value, 60, '');
    }

    /**
     * Single bulk DB lookup for all candidate handles so we don't run
     * one query per suggestion. Excludes the current user (they
     * obviously already own their old handle).
     */
    private static function existingHandlesLowered(array $candidates, int $excludeUserId): array
    {
        $lowered = [];
        foreach ($candidates as $c) {
            $n = self::normalise($c);
            if ($n !== '') $lowered[$n] = true;
        }
        if (empty($lowered)) return [];

        $keys = array_keys($lowered);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = User::query()
            ->whereRaw("LOWER(handle) IN ($placeholders)", $keys)
            ->where('id', '!=', $excludeUserId)
            ->pluck('handle')
            ->all();

        // Task #6618 — handles are workspace-profile-scoped now; check the
        // authoritative creator_profiles store too (excluding the user's
        // own profiles so their current handle stays "available" to them).
        $profileRows = \App\Modules\User\Models\CreatorProfile::query()
            ->whereRaw("LOWER(handle) IN ($placeholders)", $keys)
            ->where('user_id', '!=', $excludeUserId)
            ->pluck('handle')
            ->all();

        $taken = [];
        foreach (array_merge($rows, $profileRows) as $h) {
            $taken[mb_strtolower((string) $h)] = true;
        }
        return $taken;
    }
}
