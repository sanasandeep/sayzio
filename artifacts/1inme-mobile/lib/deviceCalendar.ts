import { Alert, Linking, Platform } from "react-native";

import type { CalendarEventItem } from "@/lib/api/calendars";

// One-tap "Add to my calendar" for events from followed calendars.
//
// On native (iOS/Android) we write straight into the device calendar with
// expo-calendar, requesting permission on first use. On web — where
// expo-calendar has no implementation — we fall back to generating a standard
// .ics file and handing it to the browser so the user can open it in whatever
// calendar app they use.

export type AddEventResult =
  | { status: "added" }
  | { status: "denied" }
  | { status: "unavailable" }
  | { status: "web-download" }
  | { status: "error"; message: string };

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
async function pickWritableCalendarId(Calendar: typeof import("expo-calendar")): Promise<string> {
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

/** Build a single-event VCALENDAR string for the web fallback. */
function buildIcs(event: CalendarEventItem, start: Date, end: Date): string {
  const lines = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//Sayzio//Calendar//EN",
    "CALSCALE:GREGORIAN",
    "BEGIN:VEVENT",
    `UID:sayzio-event-${event.id}@1in.me`,
    `DTSTAMP:${icsStamp(new Date())}`,
    `DTSTART:${icsStamp(start)}`,
    `DTEND:${icsStamp(end)}`,
    `SUMMARY:${icsEscape(event.title || "Event")}`,
  ];
  if (event.description) lines.push(`DESCRIPTION:${icsEscape(event.description)}`);
  if (event.location) lines.push(`LOCATION:${icsEscape(event.location)}`);
  lines.push("END:VEVENT", "END:VCALENDAR");
  return lines.join("\r\n");
}

/** Trigger an .ics download in the browser (web fallback). */
function downloadIcsOnWeb(event: CalendarEventItem, start: Date, end: Date): void {
  const ics = buildIcs(event, start, end);
  const blob = new Blob([ics], { type: "text/calendar;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  const slug = (event.title || "event").toLowerCase().replace(/[^a-z0-9]+/g, "-").slice(0, 40);
  a.download = `${slug || "event"}.ics`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** Write one event into the device calendar (or download an .ics on web). */
export async function addEventToDeviceCalendar(
  event: CalendarEventItem,
): Promise<AddEventResult> {
  const bounds = eventBounds(event);
  if (!bounds) return { status: "error", message: "This event has no start time." };

  if (Platform.OS === "web") {
    try {
      downloadIcsOnWeb(event, bounds.start, bounds.end);
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

    const calendarId = await pickWritableCalendarId(Calendar);
    await Calendar.createEventAsync(calendarId, {
      title: event.title || "Event",
      startDate: bounds.start,
      endDate: bounds.end,
      allDay: event.all_day,
      location: event.location ?? undefined,
      notes: event.description ?? undefined,
      timeZone: event.timezone || undefined,
    });
    return { status: "added" };
  } catch (e) {
    return { status: "error", message: (e as Error)?.message ?? "Couldn't add the event." };
  }
}

/** Convenience wrapper that surfaces the right Alert/message for each outcome. */
export async function addEventWithFeedback(event: CalendarEventItem): Promise<boolean> {
  const result = await addEventToDeviceCalendar(event);
  switch (result.status) {
    case "added":
      Alert.alert("Added to calendar", `"${event.title}" is now on your device calendar.`);
      return true;
    case "web-download":
      // The browser handles the downloaded .ics; no alert needed.
      return true;
    case "denied":
      Alert.alert(
        "Permission needed",
        "Allow calendar access in Settings to add events to your device calendar.",
      );
      return false;
    case "unavailable":
      Alert.alert(
        "Not available",
        "Adding to the device calendar isn't available on this build.",
      );
      return false;
    default:
      Alert.alert("Couldn't add event", result.message);
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
    Alert.alert("Couldn't open", "We couldn't open the subscription link.");
  }
}
