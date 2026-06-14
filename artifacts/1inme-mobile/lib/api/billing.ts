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
  features: string[];
  features_map?: Record<string, string | number | boolean>;
  currency: string;
  is_default: boolean;
  is_popular?: boolean;
  is_current: boolean;
  trial_days: number;
  monthly: PlanPrice;
  annual: PlanPrice;
  prices?: PriceMatrix;
};

export type PremiumFeature = {
  key: string;
  group: string;
  name: string;
  description: string;
  unit?: string;
  unlocked_by: string[];
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

export const billing = {
  plans: () => apiFetch<PlansResponse>("/billing/plans"),
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
