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

export type NotificationPreference = {
  type: string;
  label: string;
  description: string;
  in_app: boolean;
  email: boolean;
  push: boolean;
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

export async function getNotificationPreferences(): Promise<NotificationPreference[]> {
  const res = await apiFetch<{ data: { items: NotificationPreference[] } }>(
    `/me/notification-preferences`,
  );
  return res.data.items;
}

export async function updateNotificationPreferences(
  prefs: Record<string, { in_app: boolean; email: boolean; push: boolean }>,
): Promise<NotificationPreference[]> {
  const res = await apiFetch<{ data: { items: NotificationPreference[] } }>(
    `/me/notification-preferences`,
    { method: "PUT", body: JSON.stringify({ prefs }) },
  );
  return res.data.items;
}
