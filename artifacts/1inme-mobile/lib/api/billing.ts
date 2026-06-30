import { apiFetch } from "@/lib/api";

export type Currency = "USD" | "INR";

export type PlanPrice = {
  amount_minor: number;
  formatted: string | null;
};

export type PriceCycles = {
  monthly: PlanPrice;
  annual: PlanPrice;
};

/** Per-currency price matrix, e.g. { USD: {monthly, annual}, INR: {...} }. */
export type PriceMatrix = Partial<Record<Currency, PriceCycles>>;

export type Plan = {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  features_map?: Record<string, string | number | boolean>;
  feature_highlights?: string[];
  currency: string;
  is_default: boolean;
  is_popular?: boolean;
  is_current: boolean;
  trial_days: number;
  monthly: PlanPrice;
  annual: PlanPrice;
  prices?: PriceMatrix;
};

/**
 * A single feature's resolved value for ONE plan — the serialized output of
 * the Laravel `PremiumFeatures::resolveCell()` (the canonical resolver behind
 * the web comparison grid). The mobile screen renders directly from this so it
 * never re-implements the number / Unlimited / Advanced/Basic / Custom / on-off
 * resolution logic divergently.
 *  - kind      'number' (numeric allowance), 'analytics' (Basic/Advanced select)
 *              or 'bool' (plain on/off capability)
 *  - on        the plan includes / raises this feature
 *  - unlimited the numeric value is -1 (render as "Unlimited")
 *  - text      display string for numeric / analytics cells ('' for bools)
 */
export type PremiumFeatureCell = {
  kind: "number" | "analytics" | "bool";
  on: boolean;
  unlimited: boolean;
  text: string;
};

export type PremiumFeature = {
  key: string;
  group: string;
  name: string;
  description: string;
  unit?: string;
  unlocked_by: string[];
  /** Resolved per-plan cell value, keyed by plan slug. */
  cells?: Record<string, PremiumFeatureCell>;
};

export type Addon = {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: string | null;
  currency: string;
  monthly: PlanPrice;
  annual: PlanPrice;
};

export type PlansResponse = {
  data: {
    currency: string;
    currencies?: Currency[];
    plans: Plan[];
    addons: Addon[];
    premium_features?: PremiumFeature[];
  };
};

export type SetCurrencyResponse = {
  data: {
    currency: Currency;
  };
};

export type RevenueCatActivateResponse = {
  data: {
    ok: boolean;
    plan_id: number;
    cycle?: "monthly" | "annual";
    expires_at?: string | null;
    idempotent?: boolean;
  };
};

/**
 * Pick a plan's price for the selected currency + cycle. Mirrors the web
 * /pricing switcher: read from the per-currency `prices` matrix when the
 * backend supplied it, otherwise fall back to the resolved-currency
 * `monthly`/`annual` fields (older backend / backward-compat).
 */
export function planPrice(
  plan: Plan,
  currency: Currency,
  cycle: "monthly" | "annual",
): PlanPrice {
  const matrix = plan.prices?.[currency];
  if (matrix) return matrix[cycle];
  return cycle === "monthly" ? plan.monthly : plan.annual;
}

export type ScheduledDowngrade = {
  plan_id: number;
  plan_name: string;
  applies_at: string | null;
};

export type DowngradePlan = {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  amount_minor: number;
  formatted: string | null;
  lost_addons: string[];
};

export type DowngradeOptions = {
  subscription: {
    id: number;
    billing_cycle: string;
    currency: string;
    current_period_end: string | null;
  } | null;
  current_plan: {
    id: number;
    name: string | null;
    formatted: string | null;
  } | null;
  plans: DowngradePlan[];
  scheduled_downgrade: ScheduledDowngrade | null;
};

export type DowngradeMutationResponse = {
  data: {
    scheduled_downgrade: ScheduledDowngrade | null;
    message: string;
  };
};

/**
 * The user's latest subscription as returned by GET /billing/subscription.
 * `cancel_at_period_end` / `cancel_at` drive the mobile "Resume" affordance,
 * mirroring the web billing page's cancel-at-period-end → Resume flow.
 */
export type Subscription = {
  id: number;
  plan_id: number;
  plan_name: string | null;
  status: string;
  billing_cycle: string;
  current_period_start: string | null;
  current_period_end: string | null;
  cancel_at: string | null;
  cancel_at_period_end: boolean;
  scheduled_downgrade: ScheduledDowngrade | null;
  gateway: string | null;
  currency: string | null;
};

export type CancelResumeResponse = {
  data: {
    cancel_at_period_end: boolean;
    cancel_at: string | null;
    message: string;
  };
};

export const billing = {
  plans: () => apiFetch<PlansResponse>("/billing/plans"),
  downgradeOptions: () =>
    apiFetch<{ data: DowngradeOptions }>("/billing/downgrade"),
  scheduleDowngrade: (plan_id: number) =>
    apiFetch<DowngradeMutationResponse>("/billing/downgrade/schedule", {
      method: "POST",
      body: JSON.stringify({ plan_id }),
    }),
  cancelDowngrade: () =>
    apiFetch<DowngradeMutationResponse>("/billing/downgrade/cancel", {
      method: "POST",
    }),
  subscription: () =>
    apiFetch<{ data: { subscription: Subscription | null } }>(
      "/billing/subscription",
    ),
  resume: () =>
    apiFetch<CancelResumeResponse>("/billing/resume", {
      method: "POST",
    }),
  setCurrency: (currency: Currency) =>
    apiFetch<SetCurrencyResponse>("/billing/currency", {
      method: "POST",
      body: JSON.stringify({ currency }),
    }),
  activateRevenueCat: (input: {
    plan_id: number;
    cycle: "monthly" | "annual";
    entitlement?: string;
  }) =>
    apiFetch<RevenueCatActivateResponse>("/billing/revenuecat/activate", {
      method: "POST",
      body: JSON.stringify(input),
    }),
};
