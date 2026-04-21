import { apiFetch } from "@/lib/api";

export type Notification = {
  id: number;
  type: string | null;
  title: string | null;
  body: string | null;
  data: Record<string, unknown> | null;
  url: string | null;
  read_at: string | null;
  created_at: string | null;
};

export async function listNotifications(): Promise<{
  items: Notification[];
  unreadCount: number;
}> {
  const res = await apiFetch<{
    data: { items: Notification[]; meta: { unread_count: number } };
  }>(`/notifications`);
  return { items: res.data.items, unreadCount: res.data.meta.unread_count };
}

export async function markAllRead(): Promise<void> {
  await apiFetch(`/notifications/read-all`, { method: "POST" });
}

export async function markRead(id: number): Promise<void> {
  await apiFetch(`/notifications/${id}/read`, { method: "POST" });
}
