<?php

namespace App\Services;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Support\Collection;

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
 */
class PlanRecommender
{
    /**
     * Tracked usage signals — each is a key on the plan `features` blob
     * plus a closure that returns the user's current usage count for it.
     *
     * @return array<string, array{label:string, count:callable(User):int}>
     */
    private static function signals(): array
    {
        return [
            'max_links' => [
                'label' => 'links',
                'count' => fn (User $u) => (int) Link::where('user_id', $u->id)->count(),
            ],
            'max_biolinks' => [
                'label' => 'Link in Bio pages',
                'count' => fn (User $u) => (int) Link::where('user_id', $u->id)->where('type', 'biolink')->count(),
            ],
            'max_projects' => [
                'label' => 'projects',
                'count' => fn (User $u) => (int) $u->projects()->count(),
            ],
            'storage_limit_mb' => [
                'label' => 'storage',
                'count' => fn (User $u) => (int) round($u->getStorageUsedBytes() / 1048576),
            ],
            'contacts_max' => [
                'label' => 'contacts',
                'count' => fn (User $u) => (int) Contact::where('user_id', $u->id)->count(),
            ],
            'max_files' => [
                'label' => 'files',
                'count' => fn (User $u) => (int) UserFile::where('user_id', $u->id)->count(),
            ],
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
     * Per-signal usage rows with caps + percentages. Returns up to the
     * top signals where the user has *some* usage (so the banner stays
     * compact). Always includes the binding-most signal even if usage
     * is zero, so the banner has something to render.
     *
     * @return array<int, array{key:string,label:string,used:int,cap:int,pct:int,unlimited:bool}>
     */
    private static function buildUsage(User $user, ?Plan $plan): array
    {
        $features = $plan?->features ?? [];
        $rows = [];
        foreach (self::signals() as $key => $def) {
            $cap = (int) ($features[$key] ?? 0);
            // Skip signals the current plan doesn't define and that no
            // sensible default exists for; keeps the banner relevant.
            if ($cap === 0) continue;
            $used = ($def['count'])($user);
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
