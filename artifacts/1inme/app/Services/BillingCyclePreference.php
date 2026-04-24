<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cross-page memory for the visitor's preferred billing cycle
 * ("monthly" vs "annual").
 *
 * The pricing card CTAs already pass `?cycle=...` to /user/upgrade so a
 * visitor who clicks "Choose Pro" on /pricing lands on the right cycle.
 * This service adds the missing continuity for visitors who navigate
 * via the menu, refresh, or come back later — the choice is mirrored
 * into both a per-session flag and a long-lived signed cookie so it
 * survives session expiry too. First-time visitors with no recorded
 * preference still get the default ("monthly") so anonymous traffic
 * isn't surprised.
 *
 * Resolution precedence (mirrors PricingResolver's currency rules):
 *   1. `?cycle=...` query string on the current request — explicit
 *      navigational intent (e.g. clicked the upgrade-page toggle).
 *   2. Per-session flag — stored when we last resolved a cycle for
 *      this session.
 *   3. Long-lived signed cookie — survives session loss for anonymous
 *      visitors and people coming back days later.
 *   4. Default 'monthly' — first-time visitors.
 */
class BillingCyclePreference
{
    public const SESSION_KEY = 'billing_cycle_pref';
    public const COOKIE_KEY  = 'billing_cycle_pref';
    public const COOKIE_DAYS = 365;

    public const DEFAULT_CYCLE = 'monthly';

    /** Allow-list of accepted cycle identifiers. */
    public const VALID_CYCLES = ['monthly', 'annual'];

    /**
     * Resolve the cycle to render for the current request, walking the
     * precedence chain above. Always returns one of VALID_CYCLES.
     */
    public static function resolve(Request $request): string
    {
        $query = $request->query('cycle');
        if (self::isValid($query)) {
            return (string) $query;
        }

        try {
            $session = session(self::SESSION_KEY);
            if (self::isValid($session)) {
                return (string) $session;
            }
        } catch (\Throwable $e) {
            // No session bound (CLI / queue) — fall through.
        }

        $cookie = $request->cookie(self::COOKIE_KEY);
        if (self::isValid($cookie)) {
            return (string) $cookie;
        }

        return self::DEFAULT_CYCLE;
    }

    /**
     * Persist a cycle choice across sessions. Writes:
     *   - the per-request session flag (so the rest of the request and
     *     subsequent requests in the same session render correctly),
     *   - a long-lived signed cookie (so the choice survives session
     *     expiry for anonymous visitors).
     *
     * Returns the long-lived cookie object so the caller can attach it
     * to the outgoing response (Laravel's Cookie::queue() also works).
     */
    public static function remember(string $cycle): Cookie
    {
        $cycle = self::isValid($cycle) ? $cycle : self::DEFAULT_CYCLE;

        try {
            session([self::SESSION_KEY => $cycle]);
        } catch (\Throwable $e) {
            // No session bound — best-effort only.
        }

        // `secure` is left null so Laravel falls back to
        // `config('session.secure')` (which is env-driven) instead of
        // hard-coding insecure transport. Mirrors PricingResolver's
        // currency cookie shape exactly.
        return cookie(
            self::COOKIE_KEY,
            $cycle,
            self::COOKIE_DAYS * 24 * 60, // minutes
            '/',
            null,
            null,  // secure: defer to session config (env-driven)
            true   // httpOnly
        );
    }

    private static function isValid($value): bool
    {
        return is_string($value) && in_array($value, self::VALID_CYCLES, true);
    }
}
