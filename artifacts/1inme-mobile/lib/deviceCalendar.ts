import { Alert, Linking, Platform } from "react-native";

import type { CalendarEventItem } from "@/lib/api/calendars";
import { showAlert } from "@/lib/webAlert";

// One-tap "Add to my calendar" for events from followed calendars.
//
// On native (iOS/Android) we write straight into the device calendar with
// expo-calendar, requesting permission on first use. On web — where
// expo-calendar has no implementation — we fall back to generating a standard
// .ics file and handing it to the browser so the user can open it in whatever
// calendar app they use.

export type AddEventResult =
  | { status: "added" }
  | { status: "updated" }
  | { status: "duplicate" }
  | { status: "denied" }
  | { status: "unavailable" }
  | { status: "web-download" }
  | { status: "error"; message: string };

export type BulkAddResult =
  | { status: "done"; added: number; skipped: number; failed: number }
  | { status: "denied" }
  | { status: "unavailable" }
  | { status: "web-download"; count: number }
  | { status: "error"; message: string };

export type BulkSyncResult =
  | { status: "done"; updated: number; missing: number; failed: number }
  | { status: "denied" }
  | { status: "unavailable" }
  | { status: "web-download"; count: number }
  | { status: "error"; message: string };

export type RemoveEventResult =
  | { status: "removed" }
  | { status: "not-found" }
  | { status: "denied" }
  | { status: "unavailable" }
  | { status: "web-unsupported" }
  | { status: "error"; message: string };

export type BulkRemoveResult =
  | { status: "done"; removed: number; notFound: number; failed: number }
  | { status: "denied" }
  | { status: "unavailable" }
  | { status: "web-unsupported" }
  | { status: "error"; message: string };

/** Stable, per-event identifier shared with the .ics UID and notes marker. */
function eventUid(event: CalendarEventItem): string {
  return `sayzio-event-${event.id}@1in.me`;
}

/**
 * Build the device-event notes, appending a hidden-ish UID marker on its own
 * line so we can recognise events we previously added (expo-calendar exposes
 * no UID field, but `notes` is readable cross-platform). The same marker backs
 * both single-event dedupe/update and the bulk "add all" skip logic.
 */
function buildNotes(event: CalendarEventItem): string {
  const marker = `[${eventUid(event)}]`;
  const base = event.description?.trim() ?? "";
  return base ? `${base}\n\n${marker}` : marker;
}

/** Extract Sayzio event ids (as strings) from any notes containing our marker. */
function extractMarkerIds(notes: string, into: Set<string>): void {
  const re = /\[sayzio-event-(\d+)@1in\.me\]/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(notes)) !== null) into.add(m[1]);
}

function eventBounds(event: CalendarEventItem): { start: Date; end: Date } | null {
  if (!event.start_at) return null;
  const start = new Date(event.start_at);
  if (Number.isNaN(start.getTime())) return null;
  let end = event.end_at ? new Date(event.end_at) : new Date(NaN);
  if (Number.isNaN(end.getTime())) {
    // No (or invalid) end: all-day events span the day, timed events default to
    // one hour so the device calendar doesn't reject a zero-length entry.
    end = new Date(start.getTime() + (event.all_day ? 24 * 60 : 60) * 60 * 1000);
  }
  return { start, end };
}

/** Pick a calendar the user is actually allowed to write events into. */
async function pickWritableCalendarId(Calendar: ExpoCalendar): Promise<string> {
  if (Platform.OS === "ios") {
    const def = await Calendar.getDefaultCalendarAsync();
    if (def?.id) return def.id;
  }
  const calendars = await Calendar.getCalendarsAsync(Calendar.EntityTypes.EVENT);
  const writable = calendars.filter((c) => c.allowsModifications);
  const primary =
    writable.find((c) => (c as { isPrimary?: boolean }).isPrimary) ?? writable[0];
  if (primary?.id) return primary.id;
  throw new Error("No writable calendar was found on this device.");
}

function pad(n: number): string {
  return String(n).padStart(2, "0");
}

/** Format a Date as a UTC iCalendar timestamp (e.g. 20260627T140000Z). */
function icsStamp(d: Date): string {
  return (
    `${d.getUTCFullYear()}${pad(d.getUTCMonth() + 1)}${pad(d.getUTCDate())}` +
    `T${pad(d.getUTCHours())}${pad(d.getUTCMinutes())}${pad(d.getUTCSeconds())}Z`
  );
}

function icsEscape(value: string): string {
  return value
    .replace(/\\/g, "\\\\")
    .replace(/\n/g, "\\n")
    .replace(/,/g, "\\,")
    .replace(/;/g, "\\;");
}

/** Build the VEVENT lines for a single event (shared by single + bulk .ics). */
function buildVevent(event: CalendarEventItem, start: Date, end: Date): string[] {
  const lines = [
    "BEGIN:VEVENT",
    `UID:${eventUid(event)}`,
    `DTSTAMP:${icsStamp(new Date())}`,
    `DTSTART:${icsStamp(start)}`,
    `DTEND:${icsStamp(end)}`,
    `SUMMARY:${icsEscape(event.title || "Event")}`,
  ];
  if (event.description) lines.push(`DESCRIPTION:${icsEscape(event.description)}`);
  if (event.location) lines.push(`LOCATION:${icsEscape(event.location)}`);
  lines.push("END:VEVENT");
  return lines;
}

/** Build a VCALENDAR string wrapping one or more events for the web fallback. */
function buildIcs(events: CalendarEventItem[]): string {
  const lines = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//Sayzio//Calendar//EN",
    "CALSCALE:GREGORIAN",
  ];
  for (const event of events) {
    const bounds = eventBounds(event);
    if (!bounds) continue;
    lines.push(...buildVevent(event, bounds.start, bounds.end));
  }
  lines.push("END:VCALENDAR");
  return lines.join("\r\n");
}

/** Trigger an .ics download in the browser (web fallback). */
function downloadIcsOnWeb(events: CalendarEventItem[], filename: string): void {
  const ics = buildIcs(events);
  const blob = new Blob([ics], { type: "text/calendar;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** Slugify a title into a safe .ics filename stem. */
function eventSlug(event: CalendarEventItem): string {
  const slug = (event.title || "event")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .slice(0, 40);
  return slug || "event";
}

type ExpoCalendar = typeof import("expo-calendar");

/** Shared event details used by create, update, and bulk-add. */
function eventDetails(
  event: CalendarEventItem,
  bounds: { start: Date; end: Date },
) {
  return {
    title: event.title || "Event",
    startDate: bounds.start,
    endDate: bounds.end,
    allDay: event.all_day,
    location: event.location ?? undefined,
    notes: buildNotes(event),
    timeZone: event.timezone || undefined,
  };
}

/** Create a single event in an already-resolved device calendar. */
async function writeEvent(
  Calendar: ExpoCalendar,
  calendarId: string,
  event: CalendarEventItem,
  bounds: { start: Date; end: Date },
): Promise<void> {
  await Calendar.createEventAsync(calendarId, eventDetails(event, bounds));
}

/**
 * Look for an event we previously added (matched by its UID marker in the
 * notes) within a window around the event's date range. Returns the device
 * event id if found, otherwise null.
 */
async function findExistingDeviceEvent(
  Calendar: ExpoCalendar,
  event: CalendarEventItem,
  bounds: { start: Date; end: Date },
): Promise<string | null> {
  const calendars = await Calendar.getCalendarsAsync(Calendar.EntityTypes.EVENT).catch(
    () => [] as Awaited<ReturnType<ExpoCalendar["getCalendarsAsync"]>>,
  );
  const ids = calendars.map((c) => c.id);
  if (ids.length === 0) return null;

  // Pad the search window by a day each side so all-day/timezone-shifted copies
  // are still caught.
  const day = 24 * 60 * 60 * 1000;
  const from = new Date(bounds.start.getTime() - day);
  const to = new Date(bounds.end.getTime() + day);
  const events = await Calendar.getEventsAsync(ids, from, to).catch(
    () => [] as Awaited<ReturnType<ExpoCalendar["getEventsAsync"]>>,
  );

  const marker = `[${eventUid(event)}]`;
  const match = events.find((ev) => (ev.notes ?? "").includes(marker));
  return match?.id ?? null;
}

/**
 * Bulk-friendly variant of {@link findExistingDeviceEvent}: with one query over
 * the whole batch's date window, return a map from Sayzio event id (as a
 * string) to the *device* event id of the copy already present on any device
 * calendar, matched on the UID marker. `padDays` widens the search window each
 * side — dedupe uses a tight pad, sync a generous one so a copy whose time was
 * moved well away from the new time is still found by its marker.
 * Best-effort — failures just yield an empty map (no match rather than error).
 */
async function collectExistingEventMap(
  Calendar: ExpoCalendar,
  events: CalendarEventItem[],
  padDays: number,
): Promise<Map<string, string>> {
  const map = new Map<string, string>();
  const bounds = events
    .map(eventBounds)
    .filter((b): b is { start: Date; end: Date } => b !== null);
  if (bounds.length === 0) return map;

  const calendars = await Calendar.getCalendarsAsync(Calendar.EntityTypes.EVENT).catch(
    () => [] as Awaited<ReturnType<ExpoCalendar["getCalendarsAsync"]>>,
  );
  const calIds = calendars.map((c) => c.id);
  if (calIds.length === 0) return map;

  const minStart = new Date(Math.min(...bounds.map((b) => b.start.getTime())));
  const maxEnd = new Date(Math.max(...bounds.map((b) => b.end.getTime())));
  minStart.setDate(minStart.getDate() - padDays);
  maxEnd.setDate(maxEnd.getDate() + padDays);

  try {
    const existing = await Calendar.getEventsAsync(calIds, minStart, maxEnd);
    for (const ev of existing) {
      const ids = new Set<string>();
      extractMarkerIds(ev.notes ?? "", ids);
      for (const sayzioId of ids) {
        if (!map.has(sayzioId)) map.set(sayzioId, ev.id);
      }
    }
  } catch {
    // Couldn't read the calendar — fall back to no match rather than failing.
  }
  return map;
}

/**
 * The Sayzio event ids (as strings) already present on any device calendar.
 * Thin wrapper over {@link collectExistingEventMap} for the bulk-add dedupe,
 * which only needs to know *whether* an event exists, with a tight ±1d window.
 */
async function collectExistingEventIds(
  Calendar: ExpoCalendar,
  events: CalendarEventItem[],
): Promise<Set<string>> {
  const map = await collectExistingEventMap(Calendar, events, 1);
  return new Set(map.keys());
}

/**
 * Outcome of attempting to detect saved events without prompting:
 * - `ready` — permission is granted (or there's nothing dated to check); the
 *   resolved set of saved Sayzio event ids is included.
 * - `needs-permission` — saved-state detection genuinely works on this device
 *   but calendar access hasn't been granted; `canAskAgain` says whether the OS
 *   will still show the permission prompt (false ⇒ the user must enable it in
 *   Settings).
 * - `unavailable` — detection can't work here at all (web, or a build without
 *   expo-calendar), so there's nothing to hint about.
 */
export type SavedEventsDetection =
  | { status: "ready"; savedIds: Set<string> }
  | { status: "needs-permission"; canAskAgain: boolean }
  | { status: "unavailable" };

/**
 * Detect which of these events are already saved on the device calendar.
 * Reuses the same UID-marker lookup as add/remove via {@link collectExistingEventIds}.
 *
 * Crucially this checks the *existing* permission (it never prompts), so opening
 * the screen won't trigger a permission dialog. The discriminated result lets
 * callers tell "unknown because no access" (worth a hint) apart from "detection
 * isn't possible here" (web / no expo-calendar — stay silent).
 */
export async function detectSavedDeviceEvents(
  events: CalendarEventItem[],
): Promise<SavedEventsDetection> {
  const datable = events.filter((e) => eventBounds(e) !== null);
  if (datable.length === 0) return { status: "ready", savedIds: new Set() };

  if (Platform.OS === "web") return { status: "unavailable" };

  const Calendar = await import("expo-calendar").catch(() => null);
  if (!Calendar) return { status: "unavailable" };

  try {
    const { status, canAskAgain } = await Calendar.getCalendarPermissionsAsync();
    if (status !== "granted") return { status: "needs-permission", canAskAgain };
    return { status: "ready", savedIds: await collectExistingEventIds(Calendar, datable) };
  } catch {
    return { status: "unavailable" };
  }
}

/**
 * Thin wrapper over {@link detectSavedDeviceEvents} kept for callers that only
 * need the ids and treat anything else as "unknown" (defaulting to the Add
 * action). Returns `null` whenever the saved state can't be determined.
 */
export async function getSavedDeviceEventIds(
  events: CalendarEventItem[],
): Promise<Set<string> | null> {
  const result = await detectSavedDeviceEvents(events);
  return result.status === "ready" ? result.savedIds : null;
}

/** Outcome of asking for calendar access so saved-state detection can run. */
export type CalendarAccessRequest =
  | { status: "granted" }
  | { status: "denied"; openedSettings: boolean }
  | { status: "unavailable" };

/**
 * Request calendar access specifically so saved-event detection can run. If the
 * OS will still show the prompt we ask; if the user previously denied it (the OS
 * won't prompt again) we send them to the app's Settings page instead. Returns a
 * discriminated result so the caller can re-run detection / message accordingly.
 */
export async function requestCalendarAccessForDetection(): Promise<CalendarAccessRequest> {
  if (Platform.OS === "web") return { status: "unavailable" };

  const Calendar = await import("expo-calendar").catch(() => null);
  if (!Calendar) return { status: "unavailable" };

  try {
    const current = await Calendar.getCalendarPermissionsAsync();
    if (current.status === "granted") return { status: "granted" };

    // Already permanently denied — the OS won't prompt again, so the only way
    // back is the app's Settings page.
    if (!current.canAskAgain) {
      await Linking.openSettings().catch(() => {});
      return { status: "denied", openedSettings: true };
    }

    const next = await Calendar.requestCalendarPermissionsAsync();
    if (next.status === "granted") return { status: "granted" };
    if (!next.canAskAgain) {
      await Linking.openSettings().catch(() => {});
      return { status: "denied", openedSettings: true };
    }
    return { status: "denied", openedSettings: false };
  } catch {
    return { status: "unavailable" };
  }
}

/** Write one event into the device calendar (or download an .ics on web). */
export async function addEventToDeviceCalendar(
  event: CalendarEventItem,
): Promise<AddEventResult> {
  const bounds = eventBounds(event);
  if (!bounds) return { status: "error", message: "This event has no start time." };

  if (Platform.OS === "web") {
    try {
      downloadIcsOnWeb([event], `${eventSlug(event)}.ics`);
      return { status: "web-download" };
    } catch (e) {
      return { status: "error", message: (e as Error)?.message ?? "Couldn't export the event." };
    }
  }

  const Calendar = await import("expo-calendar").catch(() => null);
  if (!Calendar) return { status: "unavailable" };

  try {
    const { status } = await Calendar.requestCalendarPermissionsAsync();
    if (status !== "granted") return { status: "denied" };

    const existingId = await findExistingDeviceEvent(Calendar, event, bounds);
    if (existingId) return { status: "duplicate" };

    const calendarId = await pickWritableCalendarId(Calendar);
    await writeEvent(Calendar, calendarId, event, bounds);
    return { status: "added" };
  } catch (e) {
    return { status: "error", message: (e as Error)?.message ?? "Couldn't add the event." };
  }
}

/**
 * Update the previously-added copy of this event on the device calendar. If no
 * existing copy is found, a fresh one is created instead.
 */
export async function updateEventInDeviceCalendar(
  event: CalendarEventItem,
): Promise<AddEventResult> {
  const bounds = eventBounds(event);
  if (!bounds) return { status: "error", message: "This event has no start time." };

  if (Platform.OS === "web") {
    try {
      downloadIcsOnWeb([event], `${eventSlug(event)}.ics`);
      return { status: "web-download" };
    } catch (e) {
      return { status: "error", message: (e as Error)?.message ?? "Couldn't export the event." };
    }
  }

  const Calendar = await import("expo-calendar").catch(() => null);
  if (!Calendar) return { status: "unavailable" };

  try {
    const { status } = await Calendar.requestCalendarPermissionsAsync();
    if (status !== "granted") return { status: "denied" };

    const existingId = await findExistingDeviceEvent(Calendar, event, bounds);
    if (existingId) {
      await Calendar.updateEventAsync(existingId, eventDetails(event, bounds));
      return { status: "updated" };
    }

    const calendarId = await pickWritableCalendarId(Calendar);
    await Calendar.createEventAsync(calendarId, eventDetails(event, bounds));
    return { status: "added" };
  } catch (e) {
    return { status: "error", message: (e as Error)?.message ?? "Couldn't update the event." };
  }
}

/**
 * Remove the previously-added copy of this event from the device calendar.
 * Locates it by the same UID marker used for dedupe/update, then deletes it via
 * expo-calendar. Returns "not-found" when there's nothing to remove (e.g. the
 * user already deleted it from their calendar app).
 */
export async function removeEventFromDeviceCalendar(
  event: CalendarEventItem,
): Promise<RemoveEventResult> {
  const bounds = eventBounds(event);
  if (!bounds) return { status: "error", message: "This event has no start time." };

  // The web "add" path only downloads an .ics — there's no device event we own
  // to delete, so removal can't be performed programmatically.
  if (Platform.OS === "web") return { status: "web-unsupported" };

  const Calendar = await import("expo-calendar").catch(() => null);
  if (!Calendar) return { status: "unavailable" };

  try {
    const { status } = await Calendar.requestCalendarPermissionsAsync();
    if (status !== "granted") return { status: "denied" };

    const existingId = await findExistingDeviceEvent(Calendar, event, bounds);
    if (!existingId) return { status: "not-found" };

    await Calendar.deleteEventAsync(existingId);
    return { status: "removed" };
  } catch (e) {
    return { status: "error", message: (e as Error)?.message ?? "Couldn't remove the event." };
  }
}

/**
 * Bulk "add all" — writes every passed event into the device calendar, skipping
 * any that are already present (matched on the hidden Sayzio UID marker).
 * Reuses the same single-event writer so behaviour stays in lockstep.
 */
export async function addEventsToDeviceCalendar(
  events: CalendarEventItem[],
): Promise<BulkAddResult> {
  const datable = events.filter((e) => eventBounds(e) !== null);
  if (datable.length === 0) {
    return { status: "error", message: "There are no upcoming events to add." };
  }

  if (Platform.OS === "web") {
    try {
      downloadIcsOnWeb(datable, "sayzio-events.ics");
      return { status: "web-download", count: datable.length };
    } catch (e) {
      return { status: "error", message: (e as Error)?.message ?? "Couldn't export the events." };
    }
  }

  const Calendar = await import("expo-calendar").catch(() => null);
  if (!Calendar) return { status: "unavailable" };

  try {
    const { status } = await Calendar.requestCalendarPermissionsAsync();
    if (status !== "granted") return { status: "denied" };

    const calendarId = await pickWritableCalendarId(Calendar);
    const present = await collectExistingEventIds(Calendar, datable);

    let added = 0;
    let skipped = 0;
    let failed = 0;
    for (const event of datable) {
      const bounds = eventBounds(event);
      if (!bounds) {
        failed += 1;
        continue;
      }
      if (present.has(String(event.id))) {
        skipped += 1;
        continue;
      }
      try {
        await writeEvent(Calendar, calendarId, event, bounds);
        present.add(String(event.id));
        added += 1;
      } catch {
        failed += 1;
      }
    }
    return { status: "done", added, skipped, failed };
  } catch (e) {
    return { status: "error", message: (e as Error)?.message ?? "Couldn't add the events." };
  }
}

/**
 * Re-sync the already-added device copies of these events so they reflect the
 * latest time/title/location from Sayzio. Only events that already exist on a
 * device calendar (matched on the hidden Sayzio UID marker) are updated —
 * missing ones are left alone, never freshly created. Uses a generous search
 * window so a copy whose time was moved well away from the new time is still
 * found by its marker.
 */
export async function syncEventsInDeviceCalendar(
  events: CalendarEventItem[],
): Promise<BulkSyncResult> {
  const datable = events.filter((e) => eventBounds(e) !== null);
  if (datable.length === 0) {
    return { status: "error", message: "There are no events to sync." };
  }

  if (Platform.OS === "web") {
    // No tracked device copy on web — re-export an .ics so the user can
    // re-import the refreshed events into whatever calendar app they use.
    try {
      downloadIcsOnWeb(datable, "sayzio-events.ics");
      return { status: "web-download", count: datable.length };
    } catch (e) {
      return { status: "error", message: (e as Error)?.message ?? "Couldn't export the events." };
    }
  }

  const Calendar = await import("expo-calendar").catch(() => null);
  if (!Calendar) return { status: "unavailable" };

  try {
    const { status } = await Calendar.requestCalendarPermissionsAsync();
    if (status !== "granted") return { status: "denied" };

    // Wide window (a year each side) so a copy whose date moved far from the
    // new time is still matched by its UID marker.
    const present = await collectExistingEventMap(Calendar, datable, 366);

    let updated = 0;
    let missing = 0;
    let failed = 0;
    for (const event of datable) {
      const bounds = eventBounds(event);
      if (!bounds) {
        failed += 1;
        continue;
      }
      const deviceId = present.get(String(event.id));
      if (!deviceId) {
        missing += 1;
        continue;
      }
      try {
        await Calendar.updateEventAsync(deviceId, eventDetails(event, bounds));
        updated += 1;
      } catch {
        failed += 1;
      }
    }
    return { status: "done", updated, missing, failed };
  } catch (e) {
    return { status: "error", message: (e as Error)?.message ?? "Couldn't sync the events." };
  }
}

/**
 * Bulk "remove all" — deletes every previously-added device copy of these
 * events from the device calendar, matched on the hidden Sayzio UID marker.
 * One batched lookup (generous window so date-shifted copies are still caught
 * by their marker), then a delete per match. Events with no copy on the device
 * are reported as not-found; the whole call is a graceful no-op when none are
 * present.
 */
export async function removeEventsFromDeviceCalendar(
  events: CalendarEventItem[],
): Promise<BulkRemoveResult> {
  const datable = events.filter((e) => eventBounds(e) !== null);
  if (datable.length === 0) {
    return { status: "error", message: "There are no events to remove." };
  }

  // The web "add" path only downloads an .ics — there's no device event we own
  // to delete, so bulk removal can't be performed programmatically.
  if (Platform.OS === "web") return { status: "web-unsupported" };

  const Calendar = await import("expo-calendar").catch(() => null);
  if (!Calendar) return { status: "unavailable" };

  try {
    const { status } = await Calendar.requestCalendarPermissionsAsync();
    if (status !== "granted") return { status: "denied" };

    // Wide window (a year each side) so a copy whose date moved far from the
    // original is still matched by its UID marker — mirrors the bulk sync lookup.
    const present = await collectExistingEventMap(Calendar, datable, 366);

    let removed = 0;
    let notFound = 0;
    let failed = 0;
    for (const event of datable) {
      const deviceId = present.get(String(event.id));
      if (!deviceId) {
        notFound += 1;
        continue;
      }
      try {
        await Calendar.deleteEventAsync(deviceId);
        removed += 1;
      } catch {
        failed += 1;
      }
    }
    return { status: "done", removed, notFound, failed };
  } catch (e) {
    return { status: "error", message: (e as Error)?.message ?? "Couldn't remove the events." };
  }
}

/** Convenience wrapper that surfaces the right Alert/message for each outcome. */
export async function addEventWithFeedback(event: CalendarEventItem): Promise<boolean> {
  const result = await addEventToDeviceCalendar(event);
  switch (result.status) {
    case "added":
      showAlert("Added to calendar", `"${event.title}" is now on your device calendar.`);
      return true;
    case "duplicate":
      // Already on the calendar — offer to refresh it rather than duplicating.
      showAlert(
        "Already on your calendar",
        `"${event.title}" is already on your device calendar.`,
        [
          { text: "OK", style: "cancel" },
          {
            text: "Update it",
            onPress: () => {
              void updateEventWithFeedback(event);
            },
          },
        ],
      );
      return true;
    case "updated":
      showAlert("Updated", `"${event.title}" was updated on your device calendar.`);
      return true;
    case "web-download":
      // The browser handles the downloaded .ics; no alert needed.
      return true;
    case "denied":
      showAlert(
        "Permission needed",
        "Allow calendar access in Settings to add events to your device calendar.",
      );
      return false;
    case "unavailable":
      showAlert(
        "Not available",
        "Adding to the device calendar isn't available on this build.",
      );
      return false;
    default:
      showAlert("Couldn't add event", result.message);
      return false;
  }
}

/** Wrapper that updates the device copy of an event and reports the outcome. */
export async function updateEventWithFeedback(event: CalendarEventItem): Promise<boolean> {
  const result = await updateEventInDeviceCalendar(event);
  switch (result.status) {
    case "updated":
      showAlert("Updated", `"${event.title}" was updated on your device calendar.`);
      return true;
    case "added":
      showAlert("Added to calendar", `"${event.title}" is now on your device calendar.`);
      return true;
    case "web-download":
      // The browser handles the downloaded .ics; no alert needed.
      return true;
    case "denied":
      showAlert(
        "Permission needed",
        "Allow calendar access in Settings to update events on your device calendar.",
      );
      return false;
    case "unavailable":
      showAlert(
        "Not available",
        "Updating the device calendar isn't available on this build.",
      );
      return false;
    case "duplicate":
      // Not expected from an update, but treat as a no-op success.
      return true;
    default:
      showAlert("Couldn't update event", result.message);
      return false;
  }
}

/** Wrapper that removes the device copy of an event and reports the outcome. */
export async function removeEventWithFeedback(event: CalendarEventItem): Promise<boolean> {
  const result = await removeEventFromDeviceCalendar(event);
  switch (result.status) {
    case "removed":
      showAlert(
        "Removed from calendar",
        `"${event.title}" was removed from your device calendar.`,
      );
      return true;
    case "not-found":
      showAlert(
        "Not on your calendar",
        `"${event.title}" wasn't found on your device calendar — it may have already been removed.`,
      );
      return false;
    case "web-unsupported":
      showAlert(
        "Not available here",
        "Removing from the device calendar isn't supported on the web. Delete it from your calendar app instead.",
      );
      return false;
    case "denied":
      showAlert(
        "Permission needed",
        "Allow calendar access in Settings to remove events from your device calendar.",
      );
      return false;
    case "unavailable":
      showAlert(
        "Not available",
        "Removing from the device calendar isn't available on this build.",
      );
      return false;
    default:
      showAlert("Couldn't remove event", result.message);
      return false;
  }
}

/** Bulk wrapper: adds all events and surfaces a single summary Alert. */
export async function addEventsWithFeedback(events: CalendarEventItem[]): Promise<boolean> {
  const result = await addEventsToDeviceCalendar(events);
  switch (result.status) {
    case "done": {
      if (result.added === 0 && result.skipped > 0 && result.failed === 0) {
        showAlert(
          "Already up to date",
          `All ${result.skipped} event${result.skipped === 1 ? "" : "s"} are already on your calendar.`,
        );
        return true;
      }
      const parts = [`Added ${result.added}`];
      if (result.skipped) parts.push(`skipped ${result.skipped} already present`);
      if (result.failed) parts.push(`${result.failed} couldn't be added`);
      showAlert("Calendar updated", `${parts.join(", ")}.`);
      return result.added > 0;
    }
    case "web-download":
      // The browser handles the downloaded .ics; no alert needed.
      return true;
    case "denied":
      showAlert(
        "Permission needed",
        "Allow calendar access in Settings to add events to your device calendar.",
      );
      return false;
    case "unavailable":
      showAlert(
        "Not available",
        "Adding to the device calendar isn't available on this build.",
      );
      return false;
    default:
      showAlert("Couldn't add events", result.message);
      return false;
  }
}

/**
 * Bulk wrapper: refreshes the already-added device copies of these events and
 * surfaces a single summary Alert.
 */
export async function syncEventsWithFeedback(events: CalendarEventItem[]): Promise<boolean> {
  const result = await syncEventsInDeviceCalendar(events);
  switch (result.status) {
    case "done": {
      if (result.updated === 0) {
        showAlert(
          "Nothing to refresh",
          "None of these events are on your device calendar yet. Add them first to keep them in sync.",
        );
        return false;
      }
      const parts = [`Refreshed ${result.updated}`];
      if (result.missing) parts.push(`${result.missing} not added yet`);
      if (result.failed) parts.push(`${result.failed} couldn't be updated`);
      showAlert("Calendar up to date", `${parts.join(", ")} on your device calendar.`);
      return true;
    }
    case "web-download":
      // The browser handles the downloaded .ics; no alert needed.
      return true;
    case "denied":
      showAlert(
        "Permission needed",
        "Allow calendar access in Settings to refresh events on your device calendar.",
      );
      return false;
    case "unavailable":
      showAlert(
        "Not available",
        "Updating the device calendar isn't available on this build.",
      );
      return false;
    default:
      showAlert("Couldn't refresh events", result.message);
      return false;
  }
}

/**
 * Bulk wrapper: removes every previously-added device copy of these events and
 * surfaces a single summary Alert.
 */
export async function removeEventsWithFeedback(events: CalendarEventItem[]): Promise<boolean> {
  const result = await removeEventsFromDeviceCalendar(events);
  switch (result.status) {
    case "done": {
      if (result.removed === 0) {
        showAlert(
          "Nothing to remove",
          "None of these events were on your device calendar.",
        );
        return false;
      }
      const parts = [`Removed ${result.removed}`];
      if (result.notFound) parts.push(`${result.notFound} weren't on your calendar`);
      if (result.failed) parts.push(`${result.failed} couldn't be removed`);
      showAlert("Calendar updated", `${parts.join(", ")}.`);
      return true;
    }
    case "web-unsupported":
      showAlert(
        "Not available here",
        "Removing from the device calendar isn't supported on the web. Delete them from your calendar app instead.",
      );
      return false;
    case "denied":
      showAlert(
        "Permission needed",
        "Allow calendar access in Settings to remove events from your device calendar.",
      );
      return false;
    case "unavailable":
      showAlert(
        "Not available",
        "Removing from the device calendar isn't available on this build.",
      );
      return false;
    default:
      showAlert("Couldn't remove events", result.message);
      return false;
  }
}

/** Open the calendar's ICS subscription URL (full subscribe) in the OS handler. */
export async function subscribeToIcs(icsUrl: string): Promise<void> {
  if (!icsUrl) return;
  if (Platform.OS === "web") {
    window.open(icsUrl, "_blank", "noopener,noreferrer");
    return;
  }
  // webcal:// asks the OS to subscribe (live updates) rather than one-off import.
  const webcal = icsUrl.replace(/^https?:\/\//i, "webcal://");
  const canWebcal = await Linking.canOpenURL(webcal).catch(() => false);
  try {
    await Linking.openURL(canWebcal ? webcal : icsUrl);
  } catch {
    showAlert("Couldn't open", "We couldn't open the subscription link.");
  }
}
