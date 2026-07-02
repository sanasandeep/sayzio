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

export type DismissedNotification = Notification & {
  dismissed_at: string | null;
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

export async function listDismissedNotifications(): Promise<DismissedNotification[]> {
  const res = await apiFetch<{ data: { items: DismissedNotification[] } }>(
    `/notifications/dismissed`,
  );
  return res.data.items;
}

export async function markAllRead(): Promise<void> {
  await apiFetch(`/notifications/read-all`, { method: "POST" });
}

export async function markRead(id: number): Promise<void> {
  await apiFetch(`/notifications/${id}/read`, { method: "POST" });
}

export async function deleteNotification(id: number): Promise<void> {
  await apiFetch(`/notifications/${id}`, { method: "DELETE" });
}

export async function restoreNotification(id: number): Promise<void> {
  await apiFetch(`/notifications/${id}/restore`, { method: "POST" });
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

export type WhatsappPaymentAlerts = {
  enabled: boolean;
  has_whatsapp_number: boolean;
  // Connected number with all but the last 4 digits masked, or null when none.
  mobile_masked: string | null;
};

export async function getWhatsappPaymentAlerts(): Promise<WhatsappPaymentAlerts> {
  const res = await apiFetch<{ data: WhatsappPaymentAlerts }>(
    `/me/whatsapp-payment-alerts`,
  );
  return res.data;
}

export async function updateWhatsappPaymentAlerts(
  enabled: boolean,
): Promise<WhatsappPaymentAlerts> {
  const res = await apiFetch<{ data: WhatsappPaymentAlerts }>(
    `/me/whatsapp-payment-alerts`,
    { method: "PUT", body: JSON.stringify({ enabled }) },
  );
  return res.data;
}
