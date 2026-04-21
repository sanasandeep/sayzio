import { Platform } from "react-native";

import { getToken } from "@/lib/secure";

const FALLBACK_HOST = "1inme.com";

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
    ...((init.headers as Record<string, string>) ?? {}),
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(url, { ...init, headers });
  const text = await res.text();
  const body = text ? safeJson(text) : null;

  if (!res.ok) {
    const err: ApiError = {
      status: res.status,
      message:
        (body && (body.message || body.error)) ||
        `Request failed (${res.status})`,
      errors: body?.errors,
    };
    throw err;
  }
  return body as T;
}

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

function safeJson(text: string): any {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}
