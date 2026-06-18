import { Platform } from "react-native";
import Constants from "expo-constants";

import { getToken } from "@/lib/secure";

const FALLBACK_HOST = "1inme.com";

const APP_VERSION =
  (Constants?.expoConfig?.version as string | undefined) ?? "1.0.0";

export const MOBILE_USER_AGENT = `1INMEMobileApp/${APP_VERSION} (${Platform.OS}; expo)`;

let warnedFallback = false;

export function getBaseUrl(): string {
  const fromEnv = process.env.EXPO_PUBLIC_API_BASE_URL;
  if (fromEnv) return fromEnv.replace(/\/$/, "");
  const domain = process.env.EXPO_PUBLIC_DOMAIN;
  if (domain) return `https://${domain}`;
  if (Platform.OS === "web" && typeof window !== "undefined") {
    return window.location.origin;
  }
  // In dev/preview a missing env almost always means a misconfigured local
  // backend — silently routing OTP and OAuth at production was the source
  // of the broken-login bug. Surface it once, loudly, but never silently.
  // In production builds we still fall back to the canonical host.
  if (!warnedFallback) {
    warnedFallback = true;
    if (__DEV__ && typeof console !== "undefined") {
      console.warn(
        `[1inme] EXPO_PUBLIC_API_BASE_URL / EXPO_PUBLIC_DOMAIN is not set. ` +
          `OTP and OAuth requests will be sent to https://${FALLBACK_HOST} ` +
          `instead of your local backend. Set one of these in .env to fix login in dev.`,
      );
    }
  }
  return `https://${FALLBACK_HOST}`;
}

/**
 * Returns the configured API base URL, or null when the app is running in
 * dev/preview without an explicit env. Auth screens use this to refuse to
 * talk to production by mistake — see `(auth)/index.tsx`.
 */
export function getConfiguredBaseUrl(): string | null {
  if (process.env.EXPO_PUBLIC_API_BASE_URL) {
    return process.env.EXPO_PUBLIC_API_BASE_URL.replace(/\/$/, "");
  }
  if (process.env.EXPO_PUBLIC_DOMAIN) {
    return `https://${process.env.EXPO_PUBLIC_DOMAIN}`;
  }
  if (Platform.OS === "web" && typeof window !== "undefined") {
    return window.location.origin;
  }
  return null;
}

export type ApiError = {
  status: number;
  message: string;
  /**
   * Machine-readable error code from the `{error: {code}}` envelope, e.g.
   * `plan_upgrade_required` / `plan_limit_reached`. Used to detect plan-gated
   * actions and surface an "Upgrade your plan" prompt — see
   * `lib/upgradePrompt.ts`.
   */
  code?: string;
  errors?: Record<string, string[]>;
  /**
   * Raw `{error: {details}}` object from the envelope (when it is a keyed
   * object rather than a validation-errors map). Plan-gated rejections stamp
   * `{feature, recommended_plan, recommended_plan_name}` here so the upgrade
   * prompt can pre-select the plan that unlocks the blocked feature — see
   * `lib/upgradePrompt.ts`.
   */
  details?: Record<string, unknown>;
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
      code:
        (nested && typeof nested.code === "string" ? nested.code : null) ||
        (body && typeof body.code === "string" ? body.code : null) ||
        undefined,
      errors: body?.errors ?? nested?.details,
      details:
        nested?.details &&
        typeof nested.details === "object" &&
        !Array.isArray(nested.details)
          ? (nested.details as Record<string, unknown>)
          : undefined,
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

// ── Voice Assistant ───────────────────────────────────────────────
// Mobile parity for the web's floating mic. The backend orchestrator
// (Whisper → GPT tool loop → ElevenLabs) is the same — we just upload
// the recorded audio file as multipart and play back the base64 mp3
// the server returns.
export type VoiceMessage = { role: "user" | "assistant"; content: string };
export type VoicePendingConfirmation = {
  tool: string;
  description?: string | null;
  arguments?: Record<string, unknown>;
};
export type VoiceCredits = {
  stt: number;
  llm: number;
  tts: number;
  total: number;
};
export type VoiceTurnResponse = {
  transcript: string;
  reply: string;
  audio_base64: string | null;
  tool_results: Array<Record<string, unknown>>;
  pending_confirmations: VoicePendingConfirmation[];
  credits: VoiceCredits;
  balance: number;
  messages: VoiceMessage[];
};
export type VoiceCapability = {
  name: string;
  description?: string;
  destructive?: boolean;
  category?: string;
};
export type VoiceCapabilities = {
  enabled: boolean;
  balance: number;
  rate_limit: number;
  pricing: {
    stt_credits_per_minute: number;
    tts_credits_per_1k_chars: number;
  };
  tools: Record<string, VoiceCapability[]>;
  limitations: string[];
};

export const voiceAssistant = {
  capabilities: () =>
    apiFetch<VoiceCapabilities>("/ai/voice/capabilities"),

  /**
   * Upload a recorded clip and run one assistant turn.
   *   `audioUri`  – local file:// uri returned by expo-audio.
   *   `mimeType`  – best-guess mime ('audio/mp4' on iOS, 'audio/m4a'
   *                 on Android by default).
   *   `context`   – prior {messages} and confirmed_tools map; the same
   *                 audio blob is replayed when the user confirms a
   *                 destructive tool, exactly like the web widget.
   */
  turn: async (input: {
    audioUri: string;
    mimeType?: string;
    context?: {
      messages?: VoiceMessage[];
      confirmed_tools?: Record<string, boolean>;
    };
  }): Promise<VoiceTurnResponse> => {
    const url = `${getBaseUrl()}/api/v1/ai/voice/turn`;
    const token = await getToken();
    const fd = new FormData();
    const mime = input.mimeType || "audio/m4a";
    const ext = mime.includes("webm")
      ? "webm"
      : mime.includes("mp4") || mime.includes("m4a") || mime.includes("aac")
        ? "m4a"
        : mime.includes("3gpp") || mime.includes("amr")
          ? "3gp"
          : mime.includes("wav")
            ? "wav"
            : "audio";
    // React Native's FormData accepts the {uri, name, type} shape.
    fd.append("audio", {
      // eslint-disable-next-line @typescript-eslint/ban-ts-comment
      // @ts-ignore – RN-specific FormData entry.
      uri: input.audioUri,
      name: `voice.${ext}`,
      type: mime,
    } as any);
    fd.append(
      "context",
      JSON.stringify({
        messages: input.context?.messages ?? [],
        confirmed_tools: input.context?.confirmed_tools ?? {},
      }),
    );

    const headers: Record<string, string> = {
      Accept: "application/json",
      "User-Agent": MOBILE_USER_AGENT,
      "X-1INME-Client": MOBILE_USER_AGENT,
    };
    if (token) headers.Authorization = `Bearer ${token}`;
    // NB: do NOT set Content-Type — React Native fills in the
    // multipart boundary for us when the body is a FormData.

    const res = await fetch(url, { method: "POST", body: fd as any, headers });
    const text = await res.text();
    const body = text ? safeJson(text) : null;
    if (!res.ok) {
      const err: ApiError = {
        status: res.status,
        message:
          (body && typeof body.error === "string" && body.error) ||
          (body && typeof body.message === "string" && body.message) ||
          `Voice request failed (${res.status})`,
      };
      throw err;
    }
    return body as VoiceTurnResponse;
  },
};

function safeJson(text: string): any {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}
