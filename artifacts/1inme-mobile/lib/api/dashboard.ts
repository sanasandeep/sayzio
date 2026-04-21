import { apiFetch } from "@/lib/api";
import type { Link } from "@/lib/api/links";

export type Dashboard = {
  totals: {
    links: number;
    active_links: number;
    total_clicks: number;
    unique_clicks: number;
    nfc_writes: number;
    followers: number;
    unread_notifs: number;
  };
  by_type: { type: string; count: number; clicks: number }[];
  recent_links: Link[];
  top_link: Link | null;
};

export async function getDashboard(): Promise<Dashboard> {
  const res = await apiFetch<{ data: Dashboard }>(`/dashboard`);
  return res.data;
}
