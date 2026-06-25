<?php

namespace App\Services;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Computes a smart plan suggestion + usage signals for the logged-in
 * pricing experience. The same recommendation is reused on the public
 * /pricing page banner and the in-app /user/upgrade "Recommended for
 * you" highlight, so both surfaces stay consistent.
 *
 * Strategy (first match wins):
 *   1. If any usage gauge is at >= 70% of the current plan's cap,
 *      suggest the cheapest higher-tier plan that meaningfully raises
 *      that specific cap (drives the "you're nearly out of links" path).
 *   2. Otherwise if the user has a paid plan we recommend the next tier
 *      up by sort_order, when one exists.
 *   3. Otherwise fall back to the `is_popular` plan when it isn't the
 *      user's current plan; if it is, no recommendation is returned.
 *
 * The returned array is intentionally view-friendly so blade templates
 * can render it without further computation.
 *
 * Performance: the per-user usage counts (links, biolinks, projects,
 * storage bytes, contacts, files, custom domains) are cached for
 * {@see CACHE_TTL_SECONDS} so the busy /pricing and /user/upgrade pages
 * don't run a count query per signal on every request. The cache is also event-busted whenever
 * one of the underlying models is created or deleted (see
 * AppServiceProvider::boot) so gauges stay believable for the user
 * who just performed the write.
 */
class PlanRecommender
{
    /** Per-user TTL for the cached usage counts. */
    public const CACHE_TTL_SECONDS = 90;

    /** Cache key prefix; concrete key is {prefix}{user_id}. */
    private const CACHE_KEY_PREFIX = 'plan_recommender:counts:';

    /**
     * Tracked usage signals — each is a key on the plan `features` blob
     * paired with the human-readable label used in the upgrade banner.
     *
     * @return array<string, array{label:string}>
     */
    private static function signals(): array
    {
        return [
            'max_links'        => ['label' => 'links'],
            'max_biolinks'     => ['label' => 'Link in Bio pages'],
            'max_projects'     => ['label' => 'projects'],
            'storage_limit_mb' => ['label' => 'storage'],
            'contacts_max'     => ['label' => 'contacts'],
            'max_files'        => ['label' => 'files'],
            'max_custom_domains' => ['label' => 'custom domains'],
            'max_buzz_impressions' => ['label' => 'Buzz views / month'],
        ];
    }

    /**
     * Build the personalisation payload for $user against the active
     * plan catalogue. Returns null when there is no logged-in user.
     *
     * @return array{
     *   user: User,
     *   currentPlan: ?Plan,
     *   recommendedPlan: ?Plan,
     *   reason: ?string,
     *   usage: array<int, array{key:string,label:string,used:int,cap:int,pct:int,unlimited:bool}>,
     *   bindingKey: ?string
     * }|null
     */
    public static function for(?User $user, Collection $plans): ?array
    {
        if (!$user) {
            return null;
        }

        // Never recommend an internal (admin/staff-only) plan. Callers
        // generally pass an already-public collection, but filter here too
        // so the recommender is safe regardless of its caller.
        $plans = $plans->reject(fn (Plan $p) => (bool) $p->is_internal)->values();

        $currentPlan = $user->plan; // may be null for users without an assigned plan
        $usage = self::buildUsage($user, $currentPlan);

        // 1) Closest-binding limit — a usage gauge >= 70% triggers a
        //    targeted upgrade suggestion for that specific cap.
        $bindingKey = null;
        $bindingPct = -1;
        foreach ($usage as $u) {
            if ($u['unlimited']) continue;
            if ($u['pct'] >= 70 && $u['pct'] > $bindingPct) {
                $bindingKey = $u['key'];
                $bindingPct = $u['pct'];
            }
        }

        $recommended = null;
        $reason = null;

        if ($bindingKey) {
            $recommended = self::cheaperPlanThatRaises($plans, $currentPlan, $bindingKey);
            if ($recommended) {
                $bindingLabel = collect($usage)->firstWhere('key', $bindingKey)['label'] ?? 'this limit';
                $reason = "You're at {$bindingPct}% of your {$bindingLabel} limit — {$recommended->name} gives you a lot more headroom.";
            }
        }

        // 2) Next tier by sort_order if no binding limit was found.
        if (!$recommended) {
            $recommended = self::nextTier($plans, $currentPlan);
            if ($recommended) {
                $reason = "Step up to {$recommended->name} to unlock more limits and premium features.";
            }
        }

        // 3) Fall back to the popular plan when it isn't already current.
        if (!$recommended) {
            $popular = $plans->first(fn (Plan $p) => (bool) $p->is_popular);
            if ($popular && (!$currentPlan || $popular->id !== $currentPlan->id)) {
                $recommended = $popular;
                $reason = "Most creators pick {$popular->name} — it's our most popular plan.";
            }
        }

        return [
            'user' => $user,
            'currentPlan' => $currentPlan,
            'recommendedPlan' => $recommended,
            'reason' => $reason,
            'usage' => $usage,
            'bindingKey' => $bindingKey,
        ];
    }

    /**
     * Compare two plans by sort order/price. Used to label per-card CTAs
     * as Upgrade / Downgrade / Current / Choose.
     */
    public static function compare(?Plan $current, Plan $candidate): string
    {
        if (!$current) return 'choose';
        if ($current->id === $candidate->id) return 'current';
        if ((int) ($candidate->sort_order ?? 0) > (int) ($current->sort_order ?? 0)) return 'upgrade';
        if ((float) ($candidate->monthly_price ?? 0) > (float) ($current->monthly_price ?? 0)) return 'upgrade';
        return 'downgrade';
    }

    /**
     * Drop the cached usage counts for a user so the next pricing/upgrade
     * page render recomputes them. Called from the model-event hooks
     * registered in AppServiceProvider::boot whenever a Link, Contact,
     * UserFile, or Project is created/deleted, and exposed publicly so
     * other call-sites (e.g. tests, admin tooling) can bust on demand.
     *
     * Accepts a User, an integer user id, or null (no-op for null/0).
     */
    public static function forgetUsage(User|int|null $user): void
    {
        if ($user === null) {
            return;
        }
        $id = $user instanceof User ? (int) $user->id : (int) $user;
        if ($id <= 0) {
            return;
        }
        Cache::forget(self::cacheKey($id));
    }

    /**
     * Cache key used for a given user's count snapshot. Plan id is
     * intentionally NOT part of the key — only the raw counts are
     * cached, and the per-plan caps are reapplied on every read in
     * {@see buildUsage}. That way changing plans (or admin features
     * blob edits) takes effect immediately, and the cache-bust path
     * doesn't need to know which plan the user happened to be on.
     */
    public static function cacheKey(int $userId): string
    {
        return self::CACHE_KEY_PREFIX . $userId;
    }

    /**
     * Per-signal usage rows with caps + percentages. Returns up to the
     * top signals where the user has *some* usage (so the banner stays
     * compact). Always includes the binding-most signal even if usage
     * is zero, so the banner has something to render.
     *
     * Reads counts from the per-user cache (see {@see counts}); the plan
     * cap layer runs on every call so admin tweaks to features are
     * reflected without waiting for the TTL.
     *
     * @return array<int, array{key:string,label:string,used:int,cap:int,pct:int,unlimited:bool}>
     */
    private static function buildUsage(User $user, ?Plan $plan): array
    {
        $features = $plan?->features ?? [];
        $counts = self::counts($user);
        $rows = [];
        foreach (self::signals() as $key => $def) {
            $cap = (int) ($features[$key] ?? 0);
            // Skip signals the current plan doesn't define and that no
            // sensible default exists for; keeps the banner relevant.
            if ($cap === 0) continue;
            $used = (int) ($counts[$key] ?? 0);
            $unlimited = $cap === -1;
            $pct = $unlimited ? 0 : (int) min(100, round(($used / max($cap, 1)) * 100));
            $rows[] = [
                'key' => $key,
                'label' => $def['label'],
                'used' => $used,
                'cap' => $cap,
                'pct' => $pct,
                'unlimited' => $unlimited,
            ];
        }
        // Order: highest % first so the banner naturally surfaces the
        // closest-to-binding gauge.
        usort($rows, fn ($a, $b) => $b['pct'] <=> $a['pct']);
        return array_slice($rows, 0, 4);
    }

    /**
     * Fetch the cached count snapshot for $user, computing it on miss.
     * Short TTL keeps gauges believable; the AppServiceProvider event
     * hooks bust the entry on writes so the user who just created a
     * link doesn't see a stale "12/15 links" gauge for 90s.
     *
     * @return array<string,int>
     */
    private static function counts(User $user): array
    {
        return Cache::remember(
            self::cacheKey((int) $user->id),
            self::CACHE_TTL_SECONDS,
            fn () => self::computeCounts($user),
        );
    }

    /**
     * Run the per-user usage queries. Kept as its own method so the
     * caching wrapper above is a one-liner and tests can exercise the
     * raw computation directly when needed.
     *
     * @return array<string,int>
     */
    private static function computeCounts(User $user): array
    {
        return [
            'max_links'        => (int) Link::where('user_id', $user->id)->count(),
            'max_biolinks'     => (int) Link::where('user_id', $user->id)->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->count(),
            'max_projects'     => (int) $user->projects()->count(),
            'storage_limit_mb' => (int) round($user->getStorageUsedBytes() / 1048576),
            'contacts_max'     => (int) Contact::where('user_id', $user->id)->count(),
            'max_files'        => (int) UserFile::where('user_id', $user->id)->count(),
            'max_custom_domains' => (int) $user->domains()->count(),
            // Current-period Buzz impressions served across all the user's
            // campaigns (resets monthly). Approximate on the pricing gauge
            // — not event-busted on every public impression.
            'max_buzz_impressions' => (int) \App\Services\BuzzImpressionMeter::used((int) $user->id),
        ];
    }

    private static function cheaperPlanThatRaises(Collection $plans, ?Plan $current, string $key): ?Plan
    {
        $currentCap = (int) ($current?->features[$key] ?? 0);
        $sorted = $plans->sortBy('monthly_price')->values();
        foreach ($sorted as $p) {
            if ($current && $p->id === $current->id) continue;
            $val = (int) ($p->features[$key] ?? 0);
            if ($currentCap === -1) continue; // already unlimited
            if ($val === -1 || $val > $currentCap) {
                return $p;
            }
        }
        return null;
    }

    private static function nextTier(Collection $plans, ?Plan $current): ?Plan
    {
        if (!$current) {
            // No current plan — first non-free plan is the natural next tier.
            return $plans->first(fn (Plan $p) => (float) ($p->monthly_price ?? 0) > 0);
        }
        $sorted = $plans->sortBy('sort_order')->values();
        $found = false;
        foreach ($sorted as $p) {
            if ($found) return $p;
            if ($p->id === $current->id) $found = true;
        }
        return null;
    }
}
