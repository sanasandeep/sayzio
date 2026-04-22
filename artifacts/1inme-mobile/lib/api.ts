import { Platform } from "react-native";
import Constants from "expo-constants";

import { getToken } from "@/lib/secure";

const FALLBACK_HOST = "1inme.com";

const APP_VERSION =
  (Constants?.expoConfig?.version as string | undefined) ?? "1.0.0";

export const MOBILE_USER_AGENT = `1INMEMobileApp/${APP_VERSION} (${Platform.OS}; expo)`;

export function getBaseUrl(): string {
  const fromEnv = process.env.EXPO_PUBLIC_API_BASE_URL;
  if (fromEnv) return fromEnv.replace(/\/$/, "");
  const domain = process.env.EXPO_PUBLIC_DOMAIN;
  if (domain) return `https://${domain}`;
  if (Platform.OS === "web" && typeof window !== "undefined") {
    return window.location.origin;
  }
  return `https://${FALLBACK_HOST}`;
}

export type ApiError = {
  status: number;
  message: string;
  errors?: Record<string, string[]>;
};

export async function apiFetch<T = unknown>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const url = `${getBaseUrl()}/api/v1${path.startsWith("/") ? path : `/${path}`}`;
  const token = await getToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
    ...((init.headers as Record<string, string>) ?? {}),
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(url, { ...init, headers });
  const text = await res.text();
  const body = text ? safeJson(text) : null;

  if (!res.ok) {
    // Backend may return either a flat { message } payload or the
    // standard envelope { error: { message, code, details } }.
    const nested =
      body && typeof body.error === "object" ? body.error : null;
    const message =
      nested?.message ||
      (body && typeof body.message === "string" ? body.message : null) ||
      (body && typeof body.error === "string" ? body.error : null) ||
      `Request failed (${res.status})`;
    const err: ApiError = {
      status: res.status,
      message,
      errors: body?.errors ?? nested?.details,
    };
    throw err;
  }
  return body as T;
}

// ── Onboarding slides ─────────────────────────────────────────────
export type OnboardingSlide = {
  id: number;
  slug: string;
  category: string;
  title: string;
  body: string | null;
  image_url: string | null;
  image_urls: string[];
  sort_order: number;
};

export const onboarding = {
  slides: () =>
    apiFetch<{ items: OnboardingSlide[] }>("/onboarding/slides"),
};

// ── Wallet & coins ────────────────────────────────────────────────
export type WalletBalance = {
  enabled: boolean;
  balance: number;
  low_balance_threshold: number;
  currency: string;
  rate_coins_per_unit: number;
};
export type WalletTransaction = {
  id: number;
  type: "purchase" | "spend" | "adjustment" | "refund";
  delta_coins: number;
  balance_after: number;
  reason: string | null;
  created_at: string | null;
};
export type CoinPackage = {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  coin_amount: number;
  bonus_coins: number;
  total_coins: number;
  currency: string;
  amount_minor: number;
  formatted: string | null;
};
export type WalletPurchaseResponse = {
  invoice_id: number;
  invoice_no: string;
  amount_minor: number;
  currency: string;
  handoff: { kind: "redirect"; url: string } | { kind: "view"; view: string; data: unknown } | unknown;
};

export const wallet = {
  balance: () => apiFetch<WalletBalance>("/wallet"),
  transactions: (params: { type?: string; limit?: number } = {}) => {
    const q = new URLSearchParams();
    if (params.type) q.set("type", params.type);
    if (params.limit) q.set("limit", String(params.limit));
    const qs = q.toString();
    return apiFetch<{ items: WalletTransaction[] }>(
      `/wallet/transactions${qs ? `?${qs}` : ""}`,
    );
  },
  packages: () => apiFetch<{ items: CoinPackage[]; currency: string }>("/wallet/packages"),
  purchase: (coin_package_id: number, gateway: string) =>
    apiFetch<WalletPurchaseResponse>("/wallet/purchase", {
      method: "POST",
      body: JSON.stringify({ coin_package_id, gateway }),
    }),
};

// ── AI credits ────────────────────────────────────────────────────
export type AiCreditBalance = {
  enabled: boolean;
  balance: number;
  lifetime_purchased: number;
  lifetime_spent: number;
  wallet_to_credits_rate: number;
};
export type AiCreditTransaction = {
  id: number;
  type: "purchase" | "spend" | "refund" | "grant" | "admin_adjustment";
  delta_credits: number;
  balance_after: number;
  feature: string | null;
  model: string | null;
  tokens_in: number | null;
  tokens_out: number | null;
  reason: string | null;
  created_at: string | null;
};
export type AiCreditPack = {
  id: string;
  label: string;
  credits: number;
  wallet_cost: number;
};
export type AiCreditPurchaseResponse = {
  transaction_id: number;
  credits_added: number;
  balance: number;
};

export const aiCredits = {
  balance: async (): Promise<AiCreditBalance> => {
    const r = await apiFetch<{ data: AiCreditBalance }>("/ai/credits");
    return r.data;
  },
  transactions: async (
    limit = 25,
  ): Promise<{ items: AiCreditTransaction[] }> => {
    const r = await apiFetch<{ data: { items: AiCreditTransaction[] } }>(
      `/ai/credits/transactions?limit=${limit}`,
    );
    return r.data;
  },
  packs: async (): Promise<{ items: AiCreditPack[]; rate: number }> => {
    const r = await apiFetch<{
      data: { items: AiCreditPack[]; rate: number };
    }>("/ai/credits/packs");
    return r.data;
  },
  purchase: async (
    input:
      | { pack_id: string; idempotency_key?: string }
      | { credits: number; idempotency_key?: string },
  ): Promise<AiCreditPurchaseResponse> => {
    const r = await apiFetch<{ data: AiCreditPurchaseResponse }>(
      "/ai/credits/purchase",
      { method: "POST", body: JSON.stringify(input) },
    );
    return r.data;
  },
};

// ── AI Mind picker (Persona / Coach defaults) ─────────────────────
export type AiMindSummary = { id: number; name: string };
export type AiMindList = {
  mine: AiMindSummary[];
  platform: AiMindSummary | null;
};
export type AiMindFeature = "persona" | "coach";
export type AiMindDefaults = {
  feature: AiMindFeature;
  has_default: boolean;
  mind_ids: number[];
  include_platform: boolean;
};

export const aiMinds = {
  list: async (): Promise<AiMindList> => {
    const r = await apiFetch<{ data: AiMindList }>("/ai/minds");
    return r.data;
  },
  getDefaults: async (feature: AiMindFeature): Promise<AiMindDefaults> => {
    const r = await apiFetch<{ data: AiMindDefaults }>(
      `/ai/${feature}/defaults`,
    );
    return r.data;
  },
  saveDefaults: async (
    feature: AiMindFeature,
    input: { mind_ids: number[]; include_platform: boolean },
  ): Promise<AiMindDefaults> => {
    const r = await apiFetch<{ data: AiMindDefaults }>(
      `/ai/${feature}/defaults`,
      { method: "PUT", body: JSON.stringify(input) },
    );
    return r.data;
  },
  clearDefaults: async (feature: AiMindFeature): Promise<AiMindDefaults> => {
    const r = await apiFetch<{ data: AiMindDefaults }>(
      `/ai/${feature}/defaults`,
      { method: "DELETE" },
    );
    return r.data;
  },
};

function safeJson(text: string): any {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}
