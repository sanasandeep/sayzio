import * as Device from "expo-device";
import * as Notifications from "expo-notifications";
import { Platform } from "react-native";

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

/** Drop the local alarm for a deleted / completed note. */
export async function cancelNoteAlarm(noteId: number): Promise<void> {
  try {
    await Notifications.cancelScheduledNotificationAsync(noteReminderId(noteId));
  } catch {
    /* ignore */
  }
}
