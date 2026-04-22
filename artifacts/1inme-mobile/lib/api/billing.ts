import { apiFetch } from "@/lib/api";

export type PlanPrice = {
  amount_minor: number;
  formatted: string | null;
};

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
    plans: Plan[];
    addons: Addon[];
    premium_features?: PremiumFeature[];
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

export const billing = {
  plans: () => apiFetch<PlansResponse>("/billing/plans"),
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
