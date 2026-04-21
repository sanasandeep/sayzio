import { apiFetch } from "@/lib/api";

export type CalendarAccount = {
  id: number;
  provider: string;
  account_email: string | null;
  display_name: string | null;
  mirror_enabled: boolean;
  push_enabled: boolean;
  last_synced_at: string | null;
  last_sync_status: string | null;
};

export async function listCalendarAccounts(): Promise<CalendarAccount[]> {
  const res = await apiFetch<{ data: { items: CalendarAccount[] } }>(
    "/calendar/accounts",
  );
  return res.data.items;
}

export async function disconnectCalendar(id: number): Promise<void> {
  await apiFetch(`/calendar/accounts/${id}`, { method: "DELETE" });
}

export type Rsvp = {
  id: number;
  name: string | null;
  email: string | null;
  phone: string | null;
  response: string;
  plus_ones: number;
  message: string | null;
  source: string | null;
  created_at: string | null;
};

export type RsvpMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export async function listRsvps(linkId: number): Promise<{
  items: Rsvp[];
  meta: RsvpMeta;
}> {
  const res = await apiFetch<{
    data: { items: Rsvp[]; meta: RsvpMeta };
  }>(`/links/${linkId}/rsvps`);
  return res.data;
}
