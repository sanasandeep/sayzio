import * as Device from "expo-device";
import * as Notifications from "expo-notifications";
import { Platform } from "react-native";

import { listNotes } from "@/lib/api/notes";

/**
 * Local scheduled alarms for note/to-do reminders (Task #5508).
 *
 * The server delivers the canonical reminder (push + in-app + email), but a
 * local scheduled notification makes the alarm fire even when the device is
 * offline at the due time. Identifiers are keyed by note id so rescheduling
 * or deleting a note replaces/cancels exactly its own alarm. Web and
 * simulators are no-ops — this is a progressive enhancement, never required.
 */

const noteReminderId = (noteId: number) => `dialer-note-${noteId}`;

async function canSchedule(): Promise<boolean> {
  if (Platform.OS === "web" || !Device.isDevice) return false;
  const current = await Notifications.getPermissionsAsync();
  let status = (current as { status?: string }).status;
  if (status !== "granted") {
    const requested = await Notifications.requestPermissionsAsync();
    status = (requested as { status?: string }).status;
  }
  return status === "granted";
}

/** Schedule (or replace) the local alarm for one note. Past times cancel. */
export async function syncNoteAlarm(
  noteId: number,
  remindAtIso: string | null,
  title: string,
  body: string | null,
): Promise<void> {
  try {
    await Notifications.cancelScheduledNotificationAsync(noteReminderId(noteId));
  } catch {
    /* nothing scheduled yet */
  }
  if (!remindAtIso) return;
  const when = new Date(remindAtIso);
  if (Number.isNaN(when.getTime()) || when.getTime() <= Date.now()) return;
  if (!(await canSchedule())) return;

  try {
    await Notifications.scheduleNotificationAsync({
      identifier: noteReminderId(noteId),
      content: {
        title,
        body: body ?? undefined,
        data: { type: "dialer.note_due", note_id: noteId },
      },
      trigger: {
        type: Notifications.SchedulableTriggerInputTypes.DATE,
        date: when,
      },
    });
  } catch {
    /* best-effort: the server-side push still covers this reminder */
  }
}

/**
 * Re-arm all note alarms on app launch.
 *
 * expo-notifications restores scheduled alarms after reboot on most Androids,
 * but some OEM battery managers (Xiaomi/Oppo) silently drop them. Re-syncing
 * every open note with a future remind_at on launch is idempotent (identifiers
 * are keyed dialer-note-{id}, so re-scheduling replaces rather than
 * duplicates) and guarantees reminders survive reboots. Past-due reminders
 * are never re-scheduled — syncNoteAlarm cancels and skips past times.
 * Best-effort: never throws, no-op on web/simulators.
 */
export async function rearmNoteAlarms(): Promise<void> {
  if (Platform.OS === "web" || !Device.isDevice) return;
  try {
    const { notes } = await listNotes();
    for (const n of notes) {
      if (n.done || !n.remind_at) continue;
      const title =
        n.title || (n.kind === "checklist" ? "To-do reminder" : "Note reminder");
      await syncNoteAlarm(n.id, n.remind_at, title, n.body);
    }
  } catch {
    /* offline or signed-out launch — the server push still covers reminders */
  }
}

/** Drop the local alarm for a deleted / completed note. */
export async function cancelNoteAlarm(noteId: number): Promise<void> {
  try {
    await Notifications.cancelScheduledNotificationAsync(noteReminderId(noteId));
  } catch {
    /* ignore */
  }
}
