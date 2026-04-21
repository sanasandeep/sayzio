import { apiFetch } from "@/lib/api";

export type Subscriber = {
  id: number;
  type: string;
  email: string | null;
  phone: string | null;
  name: string | null;
  status: string;
  source: string | null;
  is_read: boolean;
  is_starred: boolean;
  subscribed_at: string | null;
  unsubscribed_at: string | null;
  created_at: string | null;
};

export type SubscriberListResult = {
  items: Subscriber[];
  total: number;
};

export async function listSubscribers(opts: {
  q?: string;
  status?: string;
  per_page?: number;
} = {}): Promise<SubscriberListResult> {
  const params = new URLSearchParams();
  if (opts.q) params.set("q", opts.q);
  if (opts.status) params.set("status", opts.status);
  if (opts.per_page) params.set("per_page", String(opts.per_page));
  const qs = params.toString() ? `?${params}` : "";
  const res = await apiFetch<{
    data: { items: Subscriber[]; meta: { total: number } };
  }>(`/subscribers${qs}`);
  return { items: res.data.items, total: res.data.meta.total };
}

export async function unsubscribe(id: number): Promise<void> {
  await apiFetch(`/subscribers/${id}`, { method: "DELETE" });
}
