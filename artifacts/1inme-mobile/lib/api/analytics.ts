import { apiFetch } from "@/lib/api";

export type BlockSummary = {
  block_id: number;
  type: string | null;
  title: string | null;
  destination_url: string | null;
  clicks: number;
  unique_clicks: number;
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
  by_block?: BlockSummary[];
};

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

export async function getNfcCount(linkId: number): Promise<number> {
  const res = await apiFetch<{
    data: { items: unknown[]; meta: { total: number } };
  }>(`/links/${linkId}/nfc-writes?per_page=1`);
  return res.data.meta.total;
}
