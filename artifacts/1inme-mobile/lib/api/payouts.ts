import { apiFetch } from "@/lib/api";

export type PayoutProvider = {
  slug: "stripe" | "paypal" | "razorpay" | "ccbill" | "segpay" | string;
  name: string;
  short: string;
  countries: string;
  payout_speed: string;
  fees: string;
  adult_friendly: boolean;
  docs_url?: string | null;
};

export type PayoutConnection = {
  id: number;
  provider: string;
  status: string;
  status_label: string;
  status_reason?: string | null;
  payouts_enabled: boolean;
  charges_enabled: boolean;
  is_default: boolean;
  adult_friendly: boolean;
  last_sync_at?: string | null;
  account_id?: string | null;
  country?: string | null;
};

export type PayoutsState = {
  providers: PayoutProvider[];
  connections: PayoutConnection[];
  adult_enabled: boolean;
  adult_friendly_slugs: string[];
};

export async function getPayouts(): Promise<PayoutsState> {
  const res = await apiFetch<{ data: PayoutsState }>("/payouts");
  return res.data;
}

export async function startConnect(provider: string): Promise<{
  onboarding_url: string;
  connection: PayoutConnection;
}> {
  const res = await apiFetch<{
    data: { onboarding_url: string; connection: PayoutConnection };
  }>(`/payouts/${provider}/connect`, { method: "POST" });
  return res.data;
}

export async function syncConnection(id: number): Promise<PayoutConnection> {
  const res = await apiFetch<{ data: { connection: PayoutConnection } }>(
    `/payouts/${id}/sync`,
    { method: "POST" },
  );
  return res.data.connection;
}

export async function setDefaultConnection(id: number): Promise<PayoutConnection> {
  const res = await apiFetch<{ data: { connection: PayoutConnection } }>(
    `/payouts/${id}/default`,
    { method: "POST" },
  );
  return res.data.connection;
}

export async function removeConnection(id: number): Promise<void> {
  await apiFetch(`/payouts/${id}`, { method: "DELETE" });
}

export type AdultContentState = {
  enabled: boolean;
  enabled_at: string | null;
  age_verified_at: string | null;
  flag_suspended: boolean;
  flag_suspended_reason: string | null;
  has_adult_provider: boolean;
};

export async function getAdultContent(): Promise<AdultContentState> {
  const res = await apiFetch<{ data: AdultContentState }>("/adult-content");
  return res.data;
}

export type AdultContentPayload =
  | { enable: false }
  | {
      enable: true;
      confirm_age: boolean;
      confirm_legal: boolean;
      confirm_processor: boolean;
    };

export async function updateAdultContent(p: AdultContentPayload): Promise<{
  enabled: boolean;
  enabled_at?: string;
  needs_adult_provider?: boolean;
}> {
  const res = await apiFetch<{
    data: { enabled: boolean; enabled_at?: string; needs_adult_provider?: boolean };
  }>("/adult-content", { method: "POST", body: JSON.stringify(p) });
  return res.data;
}
