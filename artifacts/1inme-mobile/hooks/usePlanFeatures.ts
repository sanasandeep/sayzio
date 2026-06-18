import { useQuery } from "@tanstack/react-query";

import { billing, type Plan, type PremiumFeature } from "@/lib/api/billing";

/**
 * Shared, read-only plan-gating helper for the mobile app.
 *
 * Reads the SAME plan data the web app uses — `GET /api/v1/billing/plans`,
 * which returns each plan's `features_map` plus a `premium_features[]`
 * catalogue where every entry lists the plan slugs that `unlocked_by` it.
 * From that we derive, BEFORE the user attempts an action, whether a
 * feature or page type is available on their current plan, so screens can
 * render a proactive lock / "Upgrade" affordance instead of only reacting
 * to a server 402 after the fact.
 *
 * This adds NO new server gating — it only mirrors the existing plan data
 * for a nicer UX. Everything fails OPEN: until the plan data resolves (or
 * if it can't be loaded) nothing is reported as locked, so we never put a
 * false barrier in front of a user.
 */

// Page/link types the web gates via the `module_<type>` toggle and the
// `max_<type>` numeric cap (see LinkController::enforceLinkTypeQuota).
// Absent toggle = enabled; explicit `false` = locked. Cap of 0 = locked.
const GATED_LINK_TYPES = new Set<string>([
  "restaurant_menu",
  "reviews",
  "resume",
  "paid_page",
  "conversational",
  "slides",
  "ai_chat",
]);

function truthy(v: unknown): boolean {
  return v === true || v === 1 || v === "1" || v === "true";
}

export type PlanGate = {
  isLoading: boolean;
  /** True once plan data has loaded and a current plan was resolved. */
  ready: boolean;
  currentPlan: Plan | null;
  /** A boolean plan feature is enabled on the current plan. */
  hasFeature: (key: string) => boolean;
  /** A boolean plan feature is locked (data ready AND feature not enabled). */
  isFeatureLocked: (key: string) => boolean;
  /** A page/link type is locked on the current plan (module off or 0 cap). */
  isLinkTypeLocked: (apiType: string) => boolean;
  /**
   * The current plan's numeric cap for a key (e.g. `max_biolinks`,
   * `max_links`). Returns `-1` for unlimited and `null` when the plan
   * doesn't declare the key (no cap to enforce). Callers compare it against
   * a counted usage to gate a quota proactively.
   */
  numericLimit: (key: string) => number | null;
  /**
   * A usage-based quota is reached on the current plan: data is ready, the
   * cap is a finite number (>= 0, not unlimited), and `used` meets/exceeds
   * it. Fails OPEN — returns false until plan data resolves.
   */
  isQuotaReached: (key: string, used: number) => boolean;
};

export function usePlanFeatures(): PlanGate {
  const q = useQuery({
    queryKey: ["billing", "plans"],
    queryFn: () => billing.plans(),
    staleTime: 5 * 60 * 1000,
  });

  const plans: Plan[] = q.data?.data.plans ?? [];
  const premium: PremiumFeature[] = q.data?.data.premium_features ?? [];

  const currentPlan =
    plans.find((p) => p.is_current) ??
    plans.find((p) => p.is_default) ??
    null;

  const ready = !q.isLoading && !!currentPlan;
  const featureMap = currentPlan?.features_map ?? {};
  const premiumByKey = new Map<string, PremiumFeature>(
    premium.map((f) => [f.key, f] as const),
  );

  function hasFeature(key: string): boolean {
    // Prefer the catalogue's `unlocked_by` mapping — it's the same source
    // the web pricing/upgrade surfaces use and is authoritative.
    const pf = premiumByKey.get(key);
    if (pf && currentPlan) return pf.unlocked_by.includes(currentPlan.slug);
    // Fall back to the plan's raw features map (e.g. keys not catalogued).
    return truthy(featureMap[key]);
  }

  function isFeatureLocked(key: string): boolean {
    if (!ready) return false; // fail open until plan data resolves
    return !hasFeature(key);
  }

  function isLinkTypeLocked(apiType: string): boolean {
    if (!ready) return false;
    if (!GATED_LINK_TYPES.has(apiType)) return false;
    const moduleVal = featureMap[`module_${apiType}`];
    if (moduleVal !== undefined && !truthy(moduleVal)) return true;
    const capVal = featureMap[`max_${apiType}`];
    if (capVal !== undefined && Number(capVal) === 0) return true;
    return false;
  }

  function numericLimit(key: string): number | null {
    const v = featureMap[key];
    if (v === undefined || v === null || v === "") return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
  }

  function isQuotaReached(key: string, used: number): boolean {
    if (!ready) return false; // fail open until plan data resolves
    const cap = numericLimit(key);
    if (cap === null || cap < 0) return false; // unknown or unlimited (-1)
    return used >= cap;
  }

  return {
    isLoading: q.isLoading,
    ready,
    currentPlan,
    hasFeature,
    isFeatureLocked,
    isLinkTypeLocked,
    numericLimit,
    isQuotaReached,
  };
}
