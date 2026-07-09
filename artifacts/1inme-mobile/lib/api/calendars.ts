import { Platform } from "react-native";

import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";

// Mobile parity for the followable `calendar` link type. These mirror the
// stateless Sanctum endpoints in artifacts/1inme
// (App\Modules\Api\Controllers\MyCalendarController): the calendars the
// signed-in user owns or follows, a single public calendar with its events,
// the cross-calendar "My Calendar" agenda, today's events, and the public
// follow toggle. The bearer-token user IS the follower identity, so there is
// no viewer session to thread through.

export type CalendarSummary = {
  id: number;
  title: string;
  description: string | null;
  timezone: string;
  accent_color: string | null;
  is_public: boolean;
  followers_count: number;
  events_count: number;
  is_owner: boolean;
  is_following: boolean;
};

export type CalendarEventCalendar = {
  id: number;
  title: string;
  accent_color: string | null;
};

export type CalendarEventItem = {
  id: number;
  calendar_id: number;
  title: string;
  description: string | null;
  start_at: string | null;
  end_at: string | null;
  all_day: boolean;
  timezone: string;
  location: string | null;
  lat: number | null;
  lng: number | null;
  hashtags: string[];
  payment_url: string | null;
  params: Record<string, unknown> | null;
  calendar: CalendarEventCalendar | null;
};

export type CalendarDetail = CalendarSummary & {
  ics_url: string;
};

export type FeedMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

/** Calendars the user owns or follows, with counts + following flag. */
export async function listCalendars(): Promise<CalendarSummary[]> {
  const res = await apiFetch<{ data: { items: CalendarSummary[] } }>(
    "/calendars",
  );
  return res.data.items;
}

/** A single public (or owned) calendar with its upcoming events. */
export async function getCalendar(
  id: number,
  opts: { past?: boolean } = {},
): Promise<{ calendar: CalendarDetail; events: CalendarEventItem[] }> {
  const qs = opts.past ? "?past=1" : "";
  const res = await apiFetch<{
    data: { calendar: CalendarDetail; events: CalendarEventItem[] };
  }>(`/calendars/${id}${qs}`);
  return res.data;
}

/** Follow / unfollow a public calendar. */
export async function toggleCalendarFollow(
  id: number,
): Promise<{ following: boolean; followers_count: number }> {
  const res = await apiFetch<{
    data: { following: boolean; followers_count: number };
  }>(`/calendars/${id}/follow`, { method: "POST" });
  return res.data;
}

export type MyCalendarFilters = {
  /** 'all' | 'owned' | 'followed' — which calendars to draw events from. */
  source?: "all" | "owned" | "followed";
  /** Restrict to a single owned/followed calendar id. */
  calendar?: number | null;
  /** Inclusive ISO date (YYYY-MM-DD) lower bound. */
  from?: string | null;
  /** Inclusive ISO date (YYYY-MM-DD) upper bound. */
  to?: string | null;
  /** Single hashtag (with or without leading #). */
  tag?: string | null;
  /** Free-text search across title / description / location. */
  q?: string | null;
  /** Include events that already started/ended. */
  past?: boolean;
  page?: number;
  perPage?: number;
};

/**
 * "My Calendar" agenda — events from every calendar the user owns OR follows,
 * with the same source / date / hashtag / search filters as the web view.
 */
export async function getMyCalendar(
  filters: MyCalendarFilters = {},
): Promise<{ items: CalendarEventItem[]; meta: FeedMeta }> {
  const q = new URLSearchParams();
  if (filters.source && filters.source !== "all") q.set("source", filters.source);
  if (filters.calendar != null) q.set("calendar", String(filters.calendar));
  if (filters.from) q.set("from", filters.from);
  if (filters.to) q.set("to", filters.to);
  if (filters.tag) q.set("tag", filters.tag);
  if (filters.q) q.set("q", filters.q);
  if (filters.past) q.set("past", "1");
  if (filters.page) q.set("page", String(filters.page));
  if (filters.perPage) q.set("per_page", String(filters.perPage));
  const qs = q.toString();
  const res = await apiFetch<{
    data: { items: CalendarEventItem[]; meta: FeedMeta };
  }>(`/my-calendar${qs ? `?${qs}` : ""}`);
  return res.data;
}

/**
 * Download the "My Calendar" agenda as an ICS or CSV file — honouring the same
 * filters as {@see getMyCalendar} — and hand it to the OS share sheet. Mirrors
 * the web "My Calendar" export. The endpoint sits behind auth:sanctum, so the
 * request MUST carry the bearer token — a plain browser open would 401. On web
 * we fetch a blob and trigger an anchor download; on native we download to the
 * cache with the auth header and share the local file.
 */
export async function exportMyCalendar(
  format: "ics" | "csv",
  filters: MyCalendarFilters = {},
): Promise<void> {
  const q = new URLSearchParams();
  q.set("format", format);
  if (filters.source && filters.source !== "all") q.set("source", filters.source);
  if (filters.calendar != null) q.set("calendar", String(filters.calendar));
  if (filters.from) q.set("from", filters.from);
  if (filters.to) q.set("to", filters.to);
  if (filters.tag) q.set("tag", filters.tag);
  if (filters.q) q.set("q", filters.q);
  if (filters.past) q.set("past", "1");

  const token = await getToken();
  const url = `${getBaseUrl()}/api/v1/my-calendar/export?${q.toString()}`;
  const filename = `my-calendar-${new Date().toISOString().slice(0, 10)}.${format}`;
  const mimeType = format === "csv" ? "text/csv" : "text/calendar";
  const headers: Record<string, string> = {
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  if (Platform.OS === "web") {
    const res = await fetch(url, { headers });
    if (!res.ok) throw new Error(`Export failed (${res.status}).`);
    const blob = await res.blob();
    const href = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = href;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(href);
    return;
  }

  const FileSystem = await import("expo-file-system/legacy");
  const Sharing = await import("expo-sharing");
  const target = `${FileSystem.cacheDirectory ?? ""}${filename}`;
  const dl = await FileSystem.downloadAsync(url, target, { headers });
  if (dl.status !== 200) throw new Error(`Export failed (${dl.status}).`);
  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(dl.uri, { mimeType, dialogTitle: "Export calendar" });
  }
}

/** Today's events across owned + followed calendars (user's local timezone). */
export async function getTodayEvents(): Promise<{
  date: string;
  items: CalendarEventItem[];
}> {
  const res = await apiFetch<{
    data: { date: string; items: CalendarEventItem[] };
  }>("/my-calendar/today");
  return res.data;
}

// ── Owner-only writes ─────────────────────────────────────────────
// Mirror MyCalendarController's create/edit calendar + event CRUD. These are
// owner-only server-side; following a calendar never grants edit rights. The
// server plan-gates calendar creation (module_calendar / max_calendars) and
// event creation (max_calendar_events) with a 402 + recommended-plan hint, so
// callers should route errors through `handlePlanLockedError`.

/** Fields for creating or editing a calendar's settings. */
export type CalendarInput = {
  title: string;
  description?: string | null;
  timezone: string;
  accent_color?: string | null;
  is_public: boolean;
};

/** Fields for creating or editing a calendar event. */
export type CalendarEventInput = {
  title: string;
  description?: string | null;
  /** Wall-clock start, e.g. "2026-06-28T14:00" — parsed in `timezone`. */
  start_at: string;
  /** Optional wall-clock end (>= start). */
  end_at?: string | null;
  all_day?: boolean;
  /** Defaults to the calendar's timezone server-side when omitted. */
  timezone?: string | null;
  location?: string | null;
  lat?: number | null;
  lng?: number | null;
  /** Space/comma-separated hashtags (with or without leading #). */
  hashtags?: string | null;
  payment_url?: string | null;
};

/**
 * Event details detected from a shared page (server-side scrape of
 * JSON-LD Event / microdata / og meta — mirrors the browser extension's
 * in-page extractor). Everything but `source`/`url` is best-effort.
 */
export type ExtractedEvent = {
  title: string | null;
  description: string | null;
  location: string | null;
  /** ISO-8601 UTC start, when the page declared one. */
  start_at: string | null;
  end_at: string | null;
  image_url: string | null;
  source: "json-ld" | "microdata" | "og" | "title";
  url: string;
};

/**
 * Ask the server to fetch a URL and detect event details for the
 * Add-to-Calendar prefill. Throws on fetch/parse failure — callers
 * should swallow errors and fall back to manual fields.
 */
export async function extractEventFromUrl(
  url: string,
): Promise<ExtractedEvent> {
  const res = await apiFetch<{ data: { event: ExtractedEvent } }>(
    `/calendars/extract-event?url=${encodeURIComponent(url)}`,
  );
  return res.data.event;
}

/** Create a new followable calendar. */
export async function createCalendar(
  input: CalendarInput,
): Promise<CalendarSummary> {
  const res = await apiFetch<{ data: { calendar: CalendarSummary } }>(
    "/calendars",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.calendar;
}

/** Update an owned calendar's settings. */
export async function updateCalendar(
  id: number,
  input: CalendarInput,
): Promise<CalendarSummary> {
  const res = await apiFetch<{ data: { calendar: CalendarSummary } }>(
    `/calendars/${id}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.calendar;
}

/** Add an event to an owned calendar. */
export async function createCalendarEvent(
  calendarId: number,
  input: CalendarEventInput,
): Promise<CalendarEventItem> {
  const res = await apiFetch<{ data: { event: CalendarEventItem } }>(
    `/calendars/${calendarId}/events`,
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.event;
}

/** Edit an event on an owned calendar. */
export async function updateCalendarEvent(
  calendarId: number,
  eventId: number,
  input: CalendarEventInput,
): Promise<CalendarEventItem> {
  const res = await apiFetch<{ data: { event: CalendarEventItem } }>(
    `/calendars/${calendarId}/events/${eventId}`,
    { method: "PATCH", body: JSON.stringify(input) },
  );
  return res.data.event;
}

/** Delete an event from an owned calendar. */
export async function deleteCalendarEvent(
  calendarId: number,
  eventId: number,
): Promise<void> {
  await apiFetch(`/calendars/${calendarId}/events/${eventId}`, {
    method: "DELETE",
  });
}
