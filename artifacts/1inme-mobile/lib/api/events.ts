import AsyncStorage from "@react-native-async-storage/async-storage";

import { apiFetch } from "@/lib/api";
import { type LinkTypePairing } from "@/lib/linkPairings";

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

export type EventInfoSection = {
  heading?: string | null;
  body?: string | null;
};

/** Task #5023: one agenda slot (may be grouped by day for multi-day events). */
export type EventAgendaItem = {
  time: string | null;
  end_time: string | null;
  title: string;
  description: string | null;
  day: number | null;
};

/** Task #5023: a downloadable document attached to an event. */
export type EventDocument = {
  file_id: number;
  label: string;
  filename: string;
  size_bytes: number;
  mime: string;
  url: string;
};

/**
 * Organizer card (Task #3674) — always present when the event has a host,
 * regardless of whether that host has claimed a public handle. `handle` is
 * null when there's no public profile to link to.
 */
export type EventOrganizer = {
  name: string | null;
  avatar: string | null;
  handle: string | null;
  /**
   * Task #3736: the reusable organizer profile (User::organizerProfile()).
   * `filled` decides whether to render the rich host card (description,
   * website, contact, address, socials) or the plain avatar+name fallback —
   * branch on this flag, don't re-derive emptiness from the fields.
   */
  filled: boolean;
  logo: string | null;
  description: string | null;
  website: string | null;
  contact_name: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  address: string | null;
  /** Assoc { platform: value }; empty object when none set. */
  socials: Record<string, string>;
};

/**
 * "More from this host" preview (Task #3674) — mirrors the web event page's
 * same-host-events list, including its past-event backfill so hosts without
 * a handle still show something. Capped at 4 by the server.
 */
export type EventHostEvent = {
  alias: string;
  title: string;
  start_date: string | null;
};

export type EventItem = {
  id: number;
  alias: string;
  title: string;
  description: string | null;
  location: string | null;
  start_date: string | null;
  end_date: string | null;
  /** Organizer timezone (IANA); used to render a guest-local time line and the Google Calendar ctz. */
  timezone: string | null;
  latitude: number | null;
  longitude: number | null;
  category: string | null;
  /**
   * Curated category label + FontAwesome icon resolved server-side from the
   * shared EventCategories catalogue (Task #3615 parity). Present whenever the
   * event has a category set; render these instead of guessing from the raw
   * slug so mobile matches the web /events directory exactly.
   */
  category_label: string | null;
  category_icon: string | null;
  ticketing_enabled: boolean;
  /**
   * Event cancellation (Sayzio events): mirrors the web "cancelled" banner.
   * `cancelled_at` is an ISO8601 string, or null when the event is live.
   */
  cancelled: boolean;
  cancelled_at: string | null;
  /**
   * Task #3674: true for any free (non-ticketed) event unless the organizer
   * explicitly opted out — RSVP is on by default now, not opt-in.
   */
  rsvp_available: boolean;
  tiers: EventTier[];
  /** Hashtags, richer page content, and the interest signal. */
  hashtags: string[];
  cover_image_url: string | null;
  gallery: string[];
  info_sections: EventInfoSection[];
  required_badge_id: number | null;
  award_badge_id: number | null;
  /** Task #5023: structured agenda items for this event. */
  agenda: EventAgendaItem[];
  /** Task #5023: downloadable documents attached to this event. */
  documents: EventDocument[];
  interested_count: number;
  not_interested_count: number;
  organizer: EventOrganizer | null;
  same_host_events: EventHostEvent[];
  /** Cross-promo "Perfect pairings" cards from the shared SitePagesContent catalog. */
  pairings?: LinkTypePairing[];
  /**
   * "10x your connections" coaching tips from SitePagesContent::eventConnectionTips().
   * Encouraging, benefit-led guidance (distinct from the factual `pairings`
   * cards) nudging hosts to turn attendees into lasting followers/contacts.
   */
  connection_tips?: EventConnectionTip[];
};

/**
 * A single "10x your connections" coaching tip. `type` maps to the create
 * flow (via pairingCreatePath) and a Feather glyph (via pairingIcon); `icon`
 * is the web FontAwesome class and is ignored on mobile. Mirrors the shape
 * returned by SitePagesContent::eventConnectionTips().
 */
export type EventConnectionTip = {
  type: string;
  icon: string;
  title: string;
  tip: string;
  cta: string;
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
  checked_in_by: string | null;
  is_rsvp_ticket?: boolean;
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
  tag?: string;
  lat?: number;
  lng?: number;
  radius?: number;
  page?: number;
};

export type Paginated<T> = {
  items: T[];
  meta: { current_page: number; last_page: number; total: number };
};

/**
 * Curated event category surfaced by the directory endpoint (mirrors
 * EventCategories::CATEGORIES on the server). `slug` is what the `category`
 * filter param expects; `label`/`icon` drive the tappable chip UI so mobile
 * matches the web /events directory exactly.
 */
export type EventCategoryOption = {
  slug: string;
  label: string;
  icon: string;
};

export type EventsDirectoryResponse = Paginated<EventItem> & {
  categories: EventCategoryOption[];
  /**
   * "10x your connections" coaching tips, returned once for the directory
   * (not per event) — mirrors the web /events page rendering the tips once
   * below the listing.
   */
  connection_tips?: EventConnectionTip[];
};

export async function listEvents(
  filters: EventsDirectoryFilters = {},
): Promise<EventsDirectoryResponse> {
  const params = new URLSearchParams();
  if (filters.q) params.set("q", filters.q);
  if (filters.category) params.set("category", filters.category);
  if (filters.tag) params.set("tag", filters.tag);
  if (filters.lat != null) params.set("lat", String(filters.lat));
  if (filters.lng != null) params.set("lng", String(filters.lng));
  if (filters.radius != null) params.set("radius", String(filters.radius));
  if (filters.page) params.set("page", String(filters.page));
  const qs = params.toString();
  const res = await apiFetch<{ data: EventsDirectoryResponse }>(
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

export type EventInterestStatus = "interested" | "not_interested";

export type EventInterestResult = {
  status: EventInterestStatus;
  counts: { interested: number; not_interested: number };
};

/**
 * One-tap interest signal, separate from RSVP/ticket purchase — mirrors the
 * web `EventInterestController`. Requires sign-in (401 -> login_required).
 */
export async function setEventInterest(
  alias: string,
  status: EventInterestStatus,
): Promise<EventInterestResult> {
  const res = await apiFetch<{ data: EventInterestResult }>(
    `/events/${encodeURIComponent(alias)}/interest`,
    { method: "POST", body: JSON.stringify({ status }) },
  );
  return res.data;
}

// ── My events calendar (tickets + interested, past & future) ───────

export type MyEventsItem = {
  kind: "ticket" | "interested";
  ticket_code?: string;
  quantity?: number;
  status?: string;
  event: {
    id: number;
    alias: string;
    title: string | null;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    url: string;
  };
};

export async function getMyEvents(): Promise<MyEventsItem[]> {
  const res = await apiFetch<{ data: { items: MyEventsItem[] } }>("/me/events");
  return res.data.items;
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

// ── Owner: event create / edit (essentials) ────────────────────────

/**
 * The read-only advanced-settings summary shown on the mobile edit screen.
 * These are editable on the web only (recurrence, RSVP questions, calendar
 * sync) — mobile shows a summary + an "edit on the web" note.
 */
export type EventAdvancedSummary = {
  recurrence: string;
  rsvp_question_count: number;
  calendar_sync_mode: string;
  ticketing_enabled: boolean;
};

/** Full editable payload for an organizer-owned event (essentials only). */
export type OwnerEvent = {
  id: number;
  alias: string;
  title: string;
  description: string | null;
  location: string | null;
  start_date: string | null;
  end_date: string | null;
  timezone: string;
  visibility: string;
  capacity: number | null;
  rsvp_enabled: boolean;
  /** Event cancellation state (Sayzio events). */
  cancelled: boolean;
  cancelled_at: string | null;
  web_edit_url: string;
  advanced: EventAdvancedSummary;
};

/**
 * The cancel response: the refreshed OwnerEvent plus notification outcome.
 * When `notify_guests` was requested and the broadcast hit its rate limit,
 * `broadcast_skipped` is true and `broadcast_message` carries the reason so
 * the app can point the organizer at the broadcast screen. `notified_count`
 * is the number of guests emailed (null when no notify was requested).
 */
export type CancelEventResult = OwnerEvent & {
  notified_count: number | null;
  broadcast_skipped: boolean;
  broadcast_message: string | null;
};

export type EventInput = {
  title: string;
  description?: string | null;
  location?: string | null;
  start_date: string;
  end_date: string;
  timezone: string;
  capacity?: number | null;
  rsvp_enabled?: boolean;
  visibility?: string;
};

export async function createEvent(input: EventInput): Promise<OwnerEvent> {
  const res = await apiFetch<{ data: OwnerEvent }>("/events", {
    method: "POST",
    body: JSON.stringify(input),
  });
  return res.data;
}

export async function getOwnerEvent(linkId: number): Promise<OwnerEvent> {
  const res = await apiFetch<{ data: OwnerEvent }>(`/links/${linkId}/event`);
  return res.data;
}

export async function updateEvent(
  linkId: number,
  input: EventInput,
): Promise<OwnerEvent> {
  const res = await apiFetch<{ data: OwnerEvent }>(`/links/${linkId}/event`, {
    method: "PATCH",
    body: JSON.stringify(input),
  });
  return res.data;
}

/**
 * Officially cancel an event. When `notifyGuests` is true the server also
 * fires the cancellation broadcast to all RSVPs; if that hits the rate limit
 * the event is STILL cancelled and the result carries `broadcast_skipped`.
 */
export async function cancelEvent(
  linkId: number,
  notifyGuests: boolean,
): Promise<CancelEventResult> {
  const res = await apiFetch<{ data: CancelEventResult }>(
    `/links/${linkId}/event/cancel`,
    { method: "POST", body: JSON.stringify({ notify_guests: notifyGuests }) },
  );
  return res.data;
}

/** Reactivate a previously-cancelled event. */
export async function reactivateEvent(linkId: number): Promise<OwnerEvent> {
  const res = await apiFetch<{ data: OwnerEvent }>(
    `/links/${linkId}/event/reactivate`,
    { method: "POST" },
  );
  return res.data;
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

export async function getOwnerTickets(
  linkId: number,
  page = 1,
): Promise<Paginated<EventTicket>> {
  const res = await apiFetch<{ data: Paginated<EventTicket> }>(
    `/links/${linkId}/event-tickets?page=${page}`,
  );
  return res.data;
}

export async function refundEventTicket(
  linkId: number,
  ticketId: number,
  reason?: string,
): Promise<EventTicket> {
  const res = await apiFetch<{ data: EventTicket }>(
    `/links/${linkId}/event-tickets/${ticketId}/refund`,
    { method: "POST", body: JSON.stringify({ refund_reason: reason ?? null }) },
  );
  return res.data;
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

// ── Task #5008: Event contact exchange ─────────────────────────────

/** The current user's "My card" payload — QR SVG + public profile info. */
export type MyEventCard = {
  profile_url: string;
  handle: string | null;
  name: string | null;
  avatar_url: string | null;
  bio: string | null;
  qr_svg: string;
};

export async function getMyEventCard(): Promise<MyEventCard> {
  const res = await apiFetch<{ data: MyEventCard }>("/me/event-card");
  return res.data;
}

/** The current user's discoverability state for a specific event. */
export type DiscoverabilityState = {
  discoverable: boolean;
  event_live: boolean;
  is_attendee: boolean;
};

export async function getDiscoverability(
  alias: string,
): Promise<DiscoverabilityState> {
  const res = await apiFetch<{ data: DiscoverabilityState }>(
    `/events/${encodeURIComponent(alias)}/discoverability`,
  );
  return res.data;
}

export async function toggleDiscoverability(
  alias: string,
  discoverable: boolean,
  coords?: { lat: number; lng: number },
): Promise<{ discoverable: boolean }> {
  const res = await apiFetch<{ data: { discoverable: boolean } }>(
    `/events/${encodeURIComponent(alias)}/discoverability`,
    {
      method: "POST",
      body: JSON.stringify({
        discoverable,
        lat: coords?.lat ?? null,
        lng: coords?.lng ?? null,
      }),
    },
  );
  return res.data;
}

/** A discoverable attendee shown in the "People at this event" list. */
export type EventAttendee = {
  user: {
    id: number;
    name: string | null;
    handle: string | null;
    avatar_url: string | null;
    bio: string | null;
  };
  exchange_status: "pending" | "accepted" | "declined" | null;
  exchange_id: number | null;
  sent_by_me: boolean | null;
};

export type AttendeesResponse = {
  items: EventAttendee[];
  total: number;
  my_discoverable: boolean;
};

export async function listEventAttendees(
  alias: string,
): Promise<AttendeesResponse> {
  const res = await apiFetch<{ data: AttendeesResponse }>(
    `/events/${encodeURIComponent(alias)}/people`,
  );
  return res.data;
}

export type ExchangeResult = {
  exchange_id: number;
  status: "pending" | "accepted";
};

export async function requestContactExchange(
  alias: string,
  recipientId: number,
): Promise<ExchangeResult> {
  const res = await apiFetch<{ data: ExchangeResult }>(
    `/events/${encodeURIComponent(alias)}/exchange`,
    { method: "POST", body: JSON.stringify({ recipient_id: recipientId }) },
  );
  return res.data;
}

export async function acceptContactExchange(
  exchangeId: number,
): Promise<ExchangeResult> {
  const res = await apiFetch<{ data: ExchangeResult }>(
    `/me/contact-exchanges/${exchangeId}/accept`,
    { method: "POST" },
  );
  return res.data;
}

// ── Task #5052: "My swaps" — review + withdraw own swap requests ────

/** One of the viewer's own swap requests at an event. */
export type MyEventSwap = {
  exchange_id: number;
  status: "pending" | "accepted";
  sent_by_me: boolean;
  /** True only for pending requests the viewer sent. */
  can_cancel: boolean;
  created_at: string | null;
  accepted_at: string | null;
  other: {
    id: number;
    name: string | null;
    handle: string | null;
    avatar_url: string | null;
  } | null;
};

export async function listMyEventSwaps(
  alias: string,
): Promise<{ items: MyEventSwap[]; total: number }> {
  const res = await apiFetch<{ data: { items: MyEventSwap[]; total: number } }>(
    `/events/${encodeURIComponent(alias)}/my-swaps`,
  );
  return res.data;
}

/** Withdraw a pending swap request the viewer sent (sender-only). */
export async function cancelContactExchange(
  exchangeId: number,
): Promise<void> {
  await apiFetch(`/me/contact-exchanges/${exchangeId}/cancel`, {
    method: "POST",
  });
}

// ── Message guests: organizer → guest broadcast ─────────────────────

export type BroadcastAudience =
  | "going"
  | "waitlist"
  | "all_rsvps"
  | "ticket_holders";

export type EventBroadcast = {
  id: number;
  audience: BroadcastAudience;
  audience_label: string;
  subject: string;
  message: string;
  recipients_count: number;
  created_at: string | null;
};

export type BroadcastOverview = {
  counts: Record<BroadcastAudience, number>;
  broadcasts: EventBroadcast[];
};

export async function getEventBroadcasts(
  linkId: number,
): Promise<BroadcastOverview> {
  const res = await apiFetch<{ data: BroadcastOverview }>(
    `/links/${linkId}/broadcasts`,
  );
  return res.data;
}

export async function sendEventBroadcast(
  linkId: number,
  input: { audience: BroadcastAudience; subject: string; message: string },
): Promise<EventBroadcast> {
  const res = await apiFetch<{ data: EventBroadcast }>(
    `/links/${linkId}/broadcasts`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

// ─── Event Connect QR (Task #6687, web flow from Task #6685) ────────────
// The host's scan-to-connect QR: scanning lands guests on the tagged event
// page where one confirmation RSVPs them "yes" and follows the host.

export type EventConnectQr = {
  link: { id: number; alias: string; title: string | null };
  // Event details from ics_data for the printable poster (Task #6693).
  event: {
    name: string;
    start_date: string | null;
    all_day: boolean;
    timezone: string | null;
    location: string | null;
  };
  connect_url: string;
  qr_svg: string;
  // Best-effort: null when the server can't render PNG (no imagick backend);
  // fall back to saving the SVG in that case.
  qr_png_base64: string | null;
};

export async function getEventConnectQr(
  linkId: number,
): Promise<EventConnectQr> {
  const res = await apiFetch<{ data: EventConnectQr }>(
    `/links/${linkId}/connect-qr`,
  );
  return res.data;
}

export type EventConnectResult = {
  success: boolean;
  status?: "confirmed" | "waitlist" | string | null;
  followed?: boolean;
  manage_url?: string | null;
  message?: string;
  code?: string;
};

/**
 * Guest side: one-tap "RSVP & Connect" for a signed-in app user who opened
 * a `?src=connect_qr` event URL. Mirrors the web prompt's confirm step.
 */
export async function eventConnect(alias: string): Promise<EventConnectResult> {
  return apiFetch<EventConnectResult>(
    `/events/${encodeURIComponent(alias)}/connect`,
    { method: "POST", body: JSON.stringify({}) },
  );
}
