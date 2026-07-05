import AsyncStorage from "@react-native-async-storage/async-storage";

import { apiFetch } from "@/lib/api";

export type EventTier = {
  id: number;
  name: string;
  description: string | null;
  price_cents: number;
  currency: string;
  price_label: string;
  is_free: boolean;
  capacity: number | null;
  sold_count: number;
  remaining: number | null;
  is_sold_out: boolean;
  is_on_sale: boolean;
  is_active: boolean;
};

export type EventItem = {
  id: number;
  alias: string;
  title: string;
  description: string | null;
  location: string | null;
  start_date: string | null;
  end_date: string | null;
  latitude: number | null;
  longitude: number | null;
  category: string | null;
  ticketing_enabled: boolean;
  tiers: EventTier[];
};

export type EventTicket = {
  id: number;
  code: string;
  status: "valid" | "checked_in" | "cancelled" | "refunded";
  quantity: number;
  price_cents: number;
  currency: string;
  attendee_name: string | null;
  attendee_email: string | null;
  checked_in_at: string | null;
  created_at: string | null;
  tier: { id: number; name: string } | null;
  event: {
    id: number;
    alias: string;
    title: string;
    location: string | null;
    start_date: string | null;
  } | null;
};

export type EventsDirectoryFilters = {
  q?: string;
  category?: string;
  lat?: number;
  lng?: number;
  radius?: number;
  page?: number;
};

export type Paginated<T> = {
  items: T[];
  meta: { current_page: number; last_page: number; total: number };
};

export async function listEvents(
  filters: EventsDirectoryFilters = {},
): Promise<Paginated<EventItem>> {
  const params = new URLSearchParams();
  if (filters.q) params.set("q", filters.q);
  if (filters.category) params.set("category", filters.category);
  if (filters.lat != null) params.set("lat", String(filters.lat));
  if (filters.lng != null) params.set("lng", String(filters.lng));
  if (filters.radius != null) params.set("radius", String(filters.radius));
  if (filters.page) params.set("page", String(filters.page));
  const qs = params.toString();
  const res = await apiFetch<{ data: Paginated<EventItem> }>(
    `/events${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}

export async function getEvent(alias: string): Promise<EventItem> {
  const res = await apiFetch<{ data: EventItem }>(
    `/events/${encodeURIComponent(alias)}`,
  );
  return res.data;
}

export async function buyEventTicket(
  alias: string,
  payload: {
    tier_id: number;
    quantity?: number;
    name: string;
    email: string;
    phone?: string;
  },
): Promise<{ checkout_url: string }> {
  const res = await apiFetch<{ data: { checkout_url: string } }>(
    `/events/${encodeURIComponent(alias)}/buy`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data;
}

export async function getMyEventTickets(): Promise<Paginated<EventTicket>> {
  const res = await apiFetch<{ data: Paginated<EventTicket> }>(
    "/me/event-tickets",
  );
  return res.data;
}

export type FullEventTicket = EventTicket & {
  checkin_url: string;
  qr_svg: string;
};

// Attendees may need to show their QR at a venue with poor signal. Once a
// ticket has been fetched successfully we persist its QR payload + key
// details on-device (scoped per ticket code), so the ticket screen can fall
// back to the cached copy when the network request fails.
const TICKET_CACHE_PREFIX = "event:ticket:v1:";

function ticketCacheKey(alias: string, code: string): string {
  return `${TICKET_CACHE_PREFIX}${alias}:${code}`;
}

export async function getCachedEventTicket(
  alias: string,
  code: string,
): Promise<FullEventTicket | null> {
  if (!alias || !code) return null;
  try {
    const raw = await AsyncStorage.getItem(ticketCacheKey(alias, code));
    return raw ? (JSON.parse(raw) as FullEventTicket) : null;
  } catch {
    return null;
  }
}

async function cacheEventTicket(
  alias: string,
  code: string,
  ticket: FullEventTicket,
): Promise<void> {
  try {
    await AsyncStorage.setItem(
      ticketCacheKey(alias, code),
      JSON.stringify(ticket),
    );
  } catch {
    // Best-effort — a failed write just means no offline fallback exists,
    // which is no worse than today.
  }
}

export async function forgetCachedEventTicket(
  alias: string,
  code: string,
): Promise<void> {
  if (!alias || !code) return;
  try {
    await AsyncStorage.removeItem(ticketCacheKey(alias, code));
  } catch {
    // No-op — see cacheEventTicket comment.
  }
}

export async function getEventTicket(
  alias: string,
  code: string,
): Promise<FullEventTicket> {
  const res = await apiFetch<{ data: FullEventTicket }>(
    `/events/${encodeURIComponent(alias)}/tickets/${encodeURIComponent(code)}`,
  );
  const ticket = res.data;
  // A refunded/cancelled ticket is no longer valid for entry, so drop any
  // cached copy rather than letting a stale QR keep displaying offline.
  if (ticket.status === "refunded" || ticket.status === "cancelled") {
    await forgetCachedEventTicket(alias, code);
  } else {
    await cacheEventTicket(alias, code, ticket);
  }
  return ticket;
}

// ── Owner: tier management + door check-in ─────────────────────────

export type OwnerTicketingTotals = {
  gross_cents: number;
  sold: number;
  checked_in: number;
  refunded: number;
};

export async function getOwnerTiers(
  linkId: number,
): Promise<{ tiers: EventTier[]; totals: OwnerTicketingTotals }> {
  const res = await apiFetch<{
    data: { tiers: EventTier[]; totals: OwnerTicketingTotals };
  }>(`/links/${linkId}/event-tiers`);
  return res.data;
}

export type TierInput = {
  name: string;
  description?: string | null;
  price: number;
  currency?: string;
  capacity?: number | null;
  sales_start?: string | null;
  sales_end?: string | null;
  is_active?: boolean;
};

export async function createTier(
  linkId: number,
  input: TierInput,
): Promise<EventTier> {
  const res = await apiFetch<{ data: EventTier }>(
    `/links/${linkId}/event-tiers`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

export async function updateTier(
  linkId: number,
  tierId: number,
  input: TierInput,
): Promise<EventTier> {
  const res = await apiFetch<{ data: EventTier }>(
    `/links/${linkId}/event-tiers/${tierId}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data;
}

export async function deleteTier(
  linkId: number,
  tierId: number,
): Promise<void> {
  await apiFetch(`/links/${linkId}/event-tiers/${tierId}`, {
    method: "DELETE",
  });
}

export type CheckinResult = {
  ok: boolean;
  status: string;
  message: string;
  ticket?: EventTicket;
};

export async function checkinScan(
  linkId: number,
  code: string,
): Promise<CheckinResult> {
  const res = await apiFetch<{ data: CheckinResult }>(
    `/links/${linkId}/event-checkin`,
    { method: "POST", body: JSON.stringify({ code }) },
  );
  return res.data;
}

export type CheckinProgress = {
  totals: { sold: number; checked_in: number; remaining: number };
  tiers: { id: number; name: string; sold: number; checked_in: number }[];
  updated_at: string;
};

export async function getCheckinProgress(
  linkId: number,
): Promise<CheckinProgress> {
  const res = await apiFetch<{ data: CheckinProgress }>(
    `/links/${linkId}/event-checkin/progress`,
  );
  return res.data;
}
