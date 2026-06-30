<?php

namespace App\Modules\User\Support;

use App\Modules\Admin\Rules\NotBannedName;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic backing the public "is this @handle free?" indicator on the
 * homepage "claim your link" hero control.
 *
 * Mirrors the exact server-side handle rules enforced when a handle is
 * actually reserved (CreatorProfileController::claimHandle and
 * AuthController::applyClaimedHandle): min:3, max:30, /^[a-z0-9_]+$/i,
 * Rule::unique('users','handle') and the admin banned-names list — so the
 * instant verdict can never disagree with what sign-up accepts.
 *
 * This runs for ANONYMOUS visitors, so NotBannedName is constructed with
 * bypass disabled: there is no privileged Auth::user() to honour, and a
 * banned name must always read as unavailable here regardless of who is
 * (not) logged in. Whether a handle exists is already public via the
 * /@handle creator pages, so reporting available/taken leaks no new
 * enumeration surface.
 *
 * Returns the plain {status, available, message, suggestions} shape; the
 * caller wraps it in whatever response envelope suits its surface.
 */
class HandleAvailability
{
    public const MIN = 3;
    public const MAX = 30;
    public const REGEX = '/^[a-z0-9_]+$/i';

    /**
     * @return array{status:string, available:bool|null, message:string, suggestions:array<int,string>}
     */
    public static function check(string $handle): array
    {
        // Normalise the same way the reserve paths do (strip a leading @,
        // trim, lowercase) so the verdict matches what actually gets stored.
        $handle = strtolower(trim(ltrim(trim($handle), '@')));

        if ($handle === '') {
            return self::result('empty', null, 'Type a handle to check if it\'s free.');
        }

        if (! preg_match(self::REGEX, $handle)) {
            return self::result('invalid', false, 'Use only letters, numbers and underscores.', self::suggestionsFor($handle));
        }

        $length = mb_strlen($handle);
        if ($length < self::MIN) {
            return self::result('too_short', false, 'Too short — use at least ' . self::MIN . ' characters.', self::suggestionsFor($handle));
        }
        if ($length > self::MAX) {
            return self::result('too_long', false, 'Too long — use at most ' . self::MAX . ' characters.');
        }

        // Admin-managed banned-names list. Bypass is disabled (see class
        // docblock) so reserved names always read as unavailable.
        $banned = false;
        (new NotBannedName(false))
            ->validate('handle', $handle, function () use (&$banned) { $banned = true; });
        if ($banned) {
            return self::result('banned', false, 'That handle is reserved — try another.', self::suggestionsFor($handle));
        }

        if (self::isTaken($handle)) {
            return self::result('taken', false, 'That handle is already taken — try another.', self::suggestionsFor($handle));
        }

        return self::result('available', true, 'Nice — that handle is available!');
    }

    /**
     * @param  array<int,string>  $suggestions
     * @return array{status:string, available:bool|null, message:string, suggestions:array<int,string>}
     */
    private static function result(string $status, ?bool $available, string $message, array $suggestions = []): array
    {
        return [
            'status'      => $status,
            'available'   => $available,
            'message'     => $message,
            'suggestions' => array_values($suggestions),
        ];
    }

    /**
     * Case-insensitive uniqueness against users.handle, matching how the
     * handle is stored (always lowercased) and the Rule::unique check.
     */
    private static function isTaken(string $handle): bool
    {
        return DB::table('users')
            ->whereRaw('LOWER(handle) = ?', [mb_strtolower($handle)])
            ->exists();
    }

    /**
     * Build up to three valid, available alternatives for a handle the
     * visitor can't have. Candidates are derived from a sanitised base of
     * the typed value (invalid characters stripped) and only returned if
     * they pass every rule (format, length, not banned, not taken).
     *
     * @return array<int,string>
     */
    private static function suggestionsFor(string $handle): array
    {
        // Keep only valid characters as the seed, then bound the base so any
        // appended suffix still fits inside the MAX length.
        $base = preg_replace('/[^a-z0-9_]/i', '', mb_strtolower($handle));
        $base = (string) $base;
        if ($base === '') {
            return [];
        }
        $base = mb_substr($base, 0, self::MAX - 4);
        if ($base === '') {
            return [];
        }

        $candidates = [];
        foreach (['hq', 'official', 'app', 'real', 'co'] as $suffix) {
            $candidates[] = $base . '_' . $suffix;
        }
        foreach (['1', '01', '7', '99', '2026'] as $num) {
            $candidates[] = $base . $num;
        }
        $candidates[] = 'the' . $base;
        $candidates[] = 'my' . $base;

        $out = [];
        foreach ($candidates as $candidate) {
            if (count($out) >= 3) {
                break;
            }
            if (mb_strlen($candidate) < self::MIN || mb_strlen($candidate) > self::MAX) {
                continue;
            }
            if (! preg_match(self::REGEX, $candidate)) {
                continue;
            }
            if (in_array($candidate, $out, true)) {
                continue;
            }

            $banned = false;
            (new NotBannedName(false))
                ->validate('handle', $candidate, function () use (&$banned) { $banned = true; });
            if ($banned) {
                continue;
            }
            if (self::isTaken($candidate)) {
                continue;
            }

            $out[] = $candidate;
        }

        return $out;
    }
}
