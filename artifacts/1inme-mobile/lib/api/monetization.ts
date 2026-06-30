import { apiFetch } from "@/lib/api";

// ── Public types ─────────────────────────────────────────────────
// Mirrors what the Laravel CreatorMonetizationApiController returns.
// Source of truth: app/Modules/Api/Controllers/CreatorMonetizationApiController.php
// (tierShape / subShape).

export type SubscriptionTier = {
  id: number;
  slug: string;
  name: string;
  is_free: boolean;
  is_active: boolean;
  price_monthly_cents: number;
  price_yearly_cents: number | null;
  currency: string;
  badge: string | null;
  color: string | null;
  perks: string[];
  sort_order: number;
  yearly_discount_percent: number | null;
};

export type SubscriptionTierRef = {
  id: number;
  name: string;
  color: string | null;
  badge: string | null;
};

export type SubscriptionFanRef = {
  id: number;
  name: string;
  handle: string | null;
  avatar: string | null;
};

export type SubscriptionCreatorRef = {
  id: number;
  name: string;
  handle: string | null;
  avatar: string | null;
};

export type SubscriptionState = {
  id: number;
  status: string;
  status_label: string;
  is_current: boolean;
  billing_cycle: "monthly" | "yearly" | string;
  price_cents: number;
  currency: string;
  cancel_at_period_end: boolean;
  current_period_end: string | null;
  started_at: string | null;
  tier: SubscriptionTierRef | null;
  creator: SubscriptionCreatorRef | null;
  fan: SubscriptionFanRef | null;
};

export type CreatorEarnings = {
  this_month_cents: number;
  all_time_cents: number;
  refunds_cents: number;
  active_subscribers: number;
  currency: string;
};

export type PaymentEvent = {
  id: number;
  type: string;
  source: "sub" | "ppv" | "tip" | string;
  label: string;
  amount_cents: number;
  currency: string;
  fan: { id: number; name: string; avatar: string | null } | null;
  occurred_at: string | null;
};

export type Paginated<T> = {
  items: T[];
  meta: { current_page: number; last_page: number; total: number };
};

// Per-endpoint checkout response shapes (the backend deliberately
// returns slightly different fields per kind).
export type SubscribeCheckout = {
  checkout_url: string | null;
  subscription: SubscriptionState;
  free: boolean;
};

export type UnlockCheckout = {
  checkout_url: string | null;
  already: boolean;
};

export type TipCheckout = {
  checkout_url: string | null;
  tip_id: number;
};

// ── Public-side API (anyone can list, auth required to act) ──────

export async function listCreatorTiers(handle: string): Promise<{
  tiers: SubscriptionTier[];
  subscription: SubscriptionState | null;
  currency: string;
}> {
  const res = await apiFetch<{
    data: {
      tiers: SubscriptionTier[];
      subscription: SubscriptionState | null;
      currency: string;
    };
  }>(`/creators/${encodeURIComponent(handle)}/tiers`);
  return res.data;
}

export async function startSubscribe(
  handle: string,
  payload: {
    tier_id: number;
    cycle: "monthly" | "yearly";
    promo_code?: string | null;
    return_url?: string | null;
  },
): Promise<SubscribeCheckout> {
  const res = await apiFetch<{ data: SubscribeCheckout }>(
    `/creators/${encodeURIComponent(handle)}/subscribe`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data;
}

export async function startUnlockPost(
  handle: string,
  postId: number,
  returnUrl?: string | null,
): Promise<UnlockCheckout> {
  const res = await apiFetch<{ data: UnlockCheckout }>(
    `/creators/${encodeURIComponent(handle)}/posts/${postId}/unlock`,
    {
      method: "POST",
      body: returnUrl ? JSON.stringify({ return_url: returnUrl }) : undefined,
    },
  );
  return res.data;
}

export async function startTip(
  handle: string,
  payload: {
    amount: number; // dollars (backend multiplies by 100)
    note?: string | null;
    post_id?: number | null;
    anonymous?: boolean;
    return_url?: string | null;
  },
): Promise<TipCheckout> {
  const res = await apiFetch<{ data: TipCheckout }>(
    `/creators/${encodeURIComponent(handle)}/tip`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data;
}

export async function getMySubscription(
  handle: string,
): Promise<SubscriptionState | null> {
  const res = await apiFetch<{ data: { subscription: SubscriptionState | null } }>(
    `/creators/${encodeURIComponent(handle)}/my-subscription`,
  );
  return res.data.subscription;
}

export async function cancelMySubscription(
  handle: string,
): Promise<SubscriptionState> {
  const res = await apiFetch<{ data: { subscription: SubscriptionState } }>(
    `/creators/${encodeURIComponent(handle)}/my-subscription/cancel`,
    { method: "POST" },
  );
  return res.data.subscription;
}

export async function resumeMySubscription(
  handle: string,
): Promise<SubscriptionState> {
  const res = await apiFetch<{ data: { subscription: SubscriptionState } }>(
    `/creators/${encodeURIComponent(handle)}/my-subscription/resume`,
    { method: "POST" },
  );
  return res.data.subscription;
}

// Every creator subscription the signed-in fan holds — backs the native
// "manage subscription" screen (the renewal-reminder deep link lands here).
export async function listMySubscriptions(): Promise<SubscriptionState[]> {
  const res = await apiFetch<{ data: { items: SubscriptionState[] } }>(
    `/me/subscriptions`,
  );
  return res.data.items;
}

// ── Owner-side dashboard endpoints ────────────────────────────────

export async function getCreatorEarnings(): Promise<CreatorEarnings> {
  const res = await apiFetch<{ data: CreatorEarnings }>(`/me/creator/earnings`);
  return res.data;
}

export async function getCreatorPayments(): Promise<Paginated<PaymentEvent>> {
  const res = await apiFetch<{ data: Paginated<PaymentEvent> }>(
    `/me/creator/payments`,
  );
  return res.data;
}

export async function getCreatorSubscribers(): Promise<Paginated<SubscriptionState>> {
  const res = await apiFetch<{ data: Paginated<SubscriptionState> }>(
    `/me/creator/subscribers`,
  );
  return res.data;
}

export async function getOwnerTiers(): Promise<{ items: SubscriptionTier[] }> {
  const res = await apiFetch<{ data: { items: SubscriptionTier[] } }>(
    `/me/creator/tiers`,
  );
  return res.data;
}
