import { apiFetch } from "@/lib/api";

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
};

export async function getAnalytics(linkId: number): Promise<Analytics> {
  const res = await apiFetch<{ data: { analytics: Analytics } }>(
    `/links/${linkId}/analytics`,
  );
  return res.data.analytics;
}

export async function getNfcCount(linkId: number): Promise<number> {
  const res = await apiFetch<{
    data: { items: unknown[]; meta: { total: number } };
  }>(`/links/${linkId}/nfc-writes?per_page=1`);
  return res.data.meta.total;
}
