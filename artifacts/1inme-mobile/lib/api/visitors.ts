import { apiFetch } from "@/lib/api";

// Mobile parity for the web Visitors analytics (Task #3816). These mirror
// the account-wide (/user/visitors) and per-link (/user/links/{link}/visitors)
// pages so the Expo app can render the same totals, new/returning split,
// daily trend, and breakdowns natively — no web Chart.js dependency.

// Preset date windows shared with the web AnalyticsRangeResolver. `custom`
// is supported by the API but the mobile UI ships the presets only.
export type VisitorPeriod = "today" | "7d" | "30d" | "90d" | "year" | "all";

export type VisitorDailyPoint = {
  d: string;
  visitors: number;
  new: number;
  returning: number;
  // Per-link page only.
  returning_pct?: number;
};

export type VisitorTypeBreakdown = {
  type: string;
  label: string;
  n: number;
};

export type VisitorSourceBreakdown = {
  src: string;
  n: number;
};

export type AvailableType = {
  type: string;
  label: string;
};

export type AccountVisitors = {
  range: { from: string; to: string; period: string };
  type: string;
  available_types: AvailableType[];
  has_links: boolean;
  total_visitors: number;
  new_count: number;
  returning_count: number;
  daily_series: VisitorDailyPoint[];
  type_breakdown: VisitorTypeBreakdown[];
  source_breakdown: VisitorSourceBreakdown[];
};

export type IdentifiedVisitor = {
  id: number;
  name: string | null;
  email: string | null;
  avatar: string | null;
  visit_count: number;
  first_seen: string | null;
  last_seen: string | null;
  is_follower: boolean;
};

export type LinkVisitors = {
  link: { id: number; alias: string; title: string | null; type: string | null };
  range: { from: string; to: string; period: string };
  total_visitors: number;
  new_count: number;
  returning_count: number;
  daily_series: VisitorDailyPoint[];
  identified: IdentifiedVisitor[];
  nfc_count: number;
  nfc_recent: { id: number; created_at: string | null }[];
  ar_sessions: number;
  ar_clicks: number;
  source_breakdown: VisitorSourceBreakdown[];
  // Event links only (Task #6687): the QR Connect funnel — scans, connects
  // split new vs existing, RSVPs and follows. `null` for non-event links.
  // Task #6694 adds the daily scans-vs-connects series and range conversion
  // % to match the web Visitor Insights panel.
  qr_connect: {
    scans: number;
    connected: number;
    new_users: number;
    existing: number;
    rsvps: number;
    follows: number;
    daily: { d: string; scans: number; connects: number }[];
    conversion_pct: number | null;
  } | null;
};

export async function getAccountVisitors(opts?: {
  period?: VisitorPeriod;
  type?: string;
}): Promise<AccountVisitors> {
  const params = new URLSearchParams();
  if (opts?.period) params.set("period", opts.period);
  if (opts?.type) params.set("type", opts.type);
  const qs = params.toString() ? `?${params.toString()}` : "";
  const res = await apiFetch<{ data: AccountVisitors }>(`/me/visitors${qs}`);
  return res.data;
}

export async function getLinkVisitors(
  linkId: number,
  opts?: { period?: VisitorPeriod },
): Promise<LinkVisitors> {
  const params = new URLSearchParams();
  if (opts?.period) params.set("period", opts.period);
  const qs = params.toString() ? `?${params.toString()}` : "";
  const res = await apiFetch<{ data: LinkVisitors }>(
    `/links/${linkId}/visitors${qs}`,
  );
  return res.data;
}
