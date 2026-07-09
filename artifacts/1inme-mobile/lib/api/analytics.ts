import { apiFetch } from "@/lib/api";

export type BlockSummary = {
  block_id: number;
  type: string | null;
  title: string | null;
  destination_url: string | null;
  clicks: number;
  unique_clicks: number;
};

export type RateLimitConfig = {
  enabled: boolean;
  ip_per_min: number;
  fp_per_min: number;
};

export type AudienceEstimateRow = {
  type: string;
  label: string;
  pct: number;
};

export type AudienceEstimate = {
  data: AudienceEstimateRow[];
  generated_at?: string | null;
  credits_spent?: number;
};

export type Analytics = {
  link_id: number;
  alias: string;
  total_clicks: number;
  unique_clicks: number;
  window: { from: string; to: string };
  by_day: { day: string; clicks: number }[];
  by_country: { country: string | null; clicks: number }[];
  by_referrer: { referrer_host: string | null; clicks: number }[];
  by_device: { device_type: string | null; clicks: number }[];
  by_source: { source: string | null; clicks: number }[];
  by_visitor_type?: { type: string; count: number; pct: number }[];
  audience_estimate?: AudienceEstimate | null;
  audience_estimate_coins?: number;
  coin_balance?: number;
  by_block?: BlockSummary[];
  blocked_total?: number;
  blocked_this_week?: number;
  blocked_by_day?: { day: string; clicks: number }[];
  rate_limit?: RateLimitConfig;
};

export async function getRateLimit(linkId: number): Promise<RateLimitConfig> {
  const res = await apiFetch<{ data: { rate_limit: RateLimitConfig } }>(
    `/links/${linkId}/rate-limit`,
  );
  return res.data.rate_limit;
}

export async function updateRateLimit(
  linkId: number,
  patch: Partial<RateLimitConfig>,
): Promise<RateLimitConfig> {
  const res = await apiFetch<{ data: { rate_limit: RateLimitConfig } }>(
    `/links/${linkId}/rate-limit`,
    { method: "PATCH", body: JSON.stringify(patch) },
  );
  return res.data.rate_limit;
}

export type VisitorType = "anonymous" | "registered" | "follower" | "subscriber";

export type BlockAnalytics = {
  block: {
    id: number;
    type: string | null;
    title: string | null;
    destination_url: string | null;
    is_active: boolean;
  };
  window: { from: string; to: string };
  total_clicks: number;
  unique_clicks: number;
  by_day: { day: string; clicks: number }[];
  by_referrer: { referrer_host: string | null; clicks: number }[];
  by_device: { device_type: string | null; clicks: number }[];
  by_visitor_type: { visitor_type: VisitorType; clicks: number }[];
};

export async function getAnalytics(linkId: number): Promise<Analytics> {
  const res = await apiFetch<{ data: { analytics: Analytics } }>(
    `/links/${linkId}/analytics`,
  );
  return res.data.analytics;
}

export async function getBlockAnalytics(
  linkId: number,
  blockId: number,
  rangeDays = 30,
): Promise<BlockAnalytics> {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - rangeDays);
  const qs = `?from=${encodeURIComponent(from.toISOString())}&to=${encodeURIComponent(to.toISOString())}`;
  const res = await apiFetch<{ data: { analytics: BlockAnalytics } }>(
    `/links/${linkId}/analytics/blocks/${blockId}${qs}`,
  );
  return res.data.analytics;
}

/**
 * Run a fresh AI audience-type estimation for a link (mobile parity for the
 * web "Get AI Estimate" button). Server charges the AI-credit feature and
 * caches the result into link settings so subsequent analytics fetches
 * include it as `audience_estimate`. Plan-gated: throws an ApiError with
 * code `plan_upgrade_required` (HTTP 402) on free plans.
 *
 * If the cached estimate is still fresh (server-side cooldown, ~10 minutes)
 * the server short-circuits and returns the cached rows without charging:
 * `cached: true` + the original `generated_at` so the UI can surface a
 * gentle "estimate is fresh" note instead of silently re-charging.
 */
export async function runAudienceEstimate(linkId: number): Promise<{
  estimated: AudienceEstimateRow[];
  credits_spent: number;
  cached?: boolean;
  generated_at?: string | null;
}> {
  const res = await apiFetch<{
    data: {
      estimated: AudienceEstimateRow[];
      credits_spent: number;
      cached?: boolean;
      generated_at?: string | null;
    };
  }>(`/links/${linkId}/audience-estimate`, { method: "POST" });
  return res.data;
}

export type HeatmapPoint = {
  lat: number;
  lng: number;
  count: number;
  city: string | null;
  country_code: string | null;
  approximate: boolean;
};

export type Heatmap = {
  type: "FeatureCollection";
  features: unknown[];
  points: HeatmapPoint[];
  meta: {
    max_weight: number;
    point_count: number;
    total_clicks: number;
    shown_clicks: number;
    period_start: string;
    period_end: string;
  };
};

export type LiveHeatmapPoint = {
  id: number;
  lat: number;
  lng: number;
  city: string | null;
  country_code: string | null;
  channel: string | null;
  channel_label: string | null;
  clicked_at: string | null;
  ts: number | null;
};

export type LiveHeatmap = {
  points: LiveHeatmapPoint[];
  meta: {
    count: number;
    unique_visitors: number;
    window_seconds: number;
    server_time: string;
    server_ts: number;
    last_id: number;
  };
};

export async function getHeatmap(
  linkId: number,
  rangeDays = 30,
): Promise<Heatmap> {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - rangeDays);
  const qs = `?from=${encodeURIComponent(from.toISOString())}&to=${encodeURIComponent(to.toISOString())}`;
  const res = await apiFetch<{ data: { heatmap: Heatmap } }>(
    `/links/${linkId}/heatmap${qs}`,
  );
  return res.data.heatmap;
}

export async function getLiveHeatmap(
  linkId: number,
  opts?: { since?: number; lastId?: number },
): Promise<LiveHeatmap> {
  const params = new URLSearchParams();
  if (opts?.lastId) params.set("lastId", String(opts.lastId));
  if (opts?.since) params.set("since", String(opts.since));
  const qs = params.toString() ? `?${params.toString()}` : "";
  const res = await apiFetch<{ data: { live: LiveHeatmap } }>(
    `/links/${linkId}/heatmap/live${qs}`,
  );
  return res.data.live;
}

export async function getNfcCount(linkId: number): Promise<number> {
  const res = await apiFetch<{
    data: { items: unknown[]; meta: { total: number } };
  }>(`/links/${linkId}/nfc-writes?per_page=1`);
  return res.data.meta.total;
}
