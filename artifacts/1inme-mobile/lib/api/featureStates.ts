import { Feather } from "@expo/vector-icons";

import { apiFetch } from "@/lib/api";

/**
 * Mobile client for the app-wide "Coming soon" feature-state system, the
 * same one the web app renders. Reads every catalogue feature's resolved
 * state from `GET /api/v1/feature-states` and records "Notify me" interest
 * (deduped per user) via `POST /api/v1/feature-states/{key}/notify` — so the
 * mobile "Soon" badge, branded preview screen and notify flow stay identical
 * to the web surface without re-implementing the resolver.
 */

export type FeatureStatus = "ready" | "coming_soon";
export type FeatureReason = "auto" | "forced" | null;

export type FeatureState = {
  key: string;
  label: string;
  /** FontAwesome class from the shared catalogue (mapped to Feather below). */
  icon: string;
  /** Brand tint hex for the preview screen accent. */
  tint: string;
  blurb: string;
  capabilities: string[];
  status: FeatureStatus;
  reason: FeatureReason;
  auto_ready: boolean;
  forced: boolean;
  notified: boolean;
};

export const featureStates = {
  list: async (): Promise<FeatureState[]> => {
    const res = await apiFetch<{ data: { features: FeatureState[] } }>(
      "/feature-states",
    );
    return res?.data?.features ?? [];
  },

  notify: async (
    key: string,
  ): Promise<{ feature_key: string; notified: boolean; created: boolean }> => {
    const res = await apiFetch<{
      data: { feature_key: string; notified: boolean; created: boolean };
    }>(`/feature-states/${encodeURIComponent(key)}/notify`, {
      method: "POST",
    });
    return res.data;
  },
};

/**
 * Maps a profile-menu `href` to the catalogue feature key it belongs to, so
 * the menu can look up a "Soon" state and reroute to the branded preview.
 * Keys mirror the web `FeatureCatalog` glob-owned route areas.
 */
export const HREF_FEATURE_KEY: Record<string, string> = {
  "/connected-apps": "connected_apps",
  "/integrations": "integrations",
  "/domains": "domains",
  "/monetization": "monetization",
  "/payouts": "payouts",
};

/**
 * FontAwesome (catalogue) → Feather glyph. The API returns FA classes the
 * web renders; the mobile app uses Feather, so map per feature key with a
 * safe default.
 */
const FEATURE_FEATHER_ICON: Record<string, keyof typeof Feather.glyphMap> = {
  connected_apps: "zap",
  monetization: "dollar-sign",
  payouts: "credit-card",
  social_proofs: "bell",
  pixels: "target",
  integrations: "link",
  domains: "globe",
};

export function featherIconFor(key: string): keyof typeof Feather.glyphMap {
  return FEATURE_FEATHER_ICON[key] ?? "clock";
}
