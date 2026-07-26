import Constants from "expo-constants";
import * as Device from "expo-device";
import * as Notifications from "expo-notifications";
import { router } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { Linking, Platform } from "react-native";

import { getBaseUrl } from "@/lib/api";
import { updateNote } from "@/lib/api/notes";
import { markRead } from "@/lib/api/notifications";
import { registerPushToken, unregisterPushToken } from "@/lib/api/push";
import {
  cancelNoteAlarm,
  NOTE_MARK_DONE_ACTION,
  NOTE_REMINDER_CATEGORY,
} from "@/lib/localReminders";

let handlerConfigured = false;
let lastRegisteredToken: string | null = null;
let registering = false;

/**
 * Foreground presentation rules. By default Expo suppresses notifications
 * while the app is open; API-usage warnings are worth surfacing as a
 * banner + sound even then.
 */
export function configurePushHandler(): void {
  if (handlerConfigured) return;
  handlerConfigured = true;
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
    }),
  });
  // Register the "Mark done" quick action for note reminders. Best-effort:
  // categories are a native-only progressive enhancement (no-op on web),
  // and a tap without the action still opens the note's editor.
  if (Platform.OS !== "web") {
    Notifications.setNotificationCategoryAsync(NOTE_REMINDER_CATEGORY, [
      {
        identifier: NOTE_MARK_DONE_ACTION,
        buttonTitle: "Mark done",
        options: { opensAppToForeground: false },
      },
    ]).catch(() => {});
  }
}

/**
 * Read the permission status off a permissions response. Typed defensively
 * because `NotificationPermissionsStatus` inherits `status` from
 * expo-modules-core's `PermissionResponse`, which some node_modules layouts
 * fail to resolve for type-checking (the field always exists at runtime).
 */
function permissionStatus(
  response: Notifications.NotificationPermissionsStatus,
): string | undefined {
  return (response as { status?: string }).status;
}

/**
 * Resolve this device's Expo push token, requesting permission if needed.
 * Returns null when running on a simulator/web, when permission is denied,
 * or when no EAS projectId is configured (so the call can't succeed) —
 * push is a progressive enhancement, never a hard requirement.
 */
async function resolveExpoPushToken(): Promise<string | null> {
  if (Platform.OS === "web" || !Device.isDevice) return null;

  const current = await Notifications.getPermissionsAsync();
  let status = permissionStatus(current);
  if (status !== "granted") {
    const requested = await Notifications.requestPermissionsAsync();
    status = permissionStatus(requested);
  }
  if (status !== "granted") return null;

  if (Platform.OS === "android") {
    await Notifications.setNotificationChannelAsync("default", {
      name: "Default",
      importance: Notifications.AndroidImportance.DEFAULT,
    });
  }

  const projectId =
    (Constants?.expoConfig?.extra as { eas?: { projectId?: string } } | undefined)
      ?.eas?.projectId ?? (Constants as any)?.easConfig?.projectId;

  try {
    const res = await Notifications.getExpoPushTokenAsync(
      projectId ? { projectId } : undefined,
    );
    return res.data ?? null;
  } catch (e) {
    if (__DEV__) {
      console.warn(
        "[1inme] Could not get an Expo push token (no EAS project / dev build?):",
        e,
      );
    }
    return null;
  }
}

/**
 * Ensure this device's push token is registered with the backend. Safe to
 * call on every sign-in / unlock — it de-dupes against the last token it
 * successfully registered this session.
 */
export async function syncPushRegistration(): Promise<void> {
  if (registering) return;
  registering = true;
  try {
    const token = await resolveExpoPushToken();
    if (!token || token === lastRegisteredToken) return;
    await registerPushToken({
      token,
      platform: Platform.OS,
      device_name: Device.deviceName ?? undefined,
    });
    lastRegisteredToken = token;
  } catch (e) {
    if (__DEV__) console.warn("[1inme] push registration failed:", e);
  } finally {
    registering = false;
  }
}

/** Detach the registered token from the user (best-effort, on sign-out). */
export async function clearPushRegistration(): Promise<void> {
  const token = lastRegisteredToken;
  lastRegisteredToken = null;
  if (!token) return;
  try {
    await unregisterPushToken(token);
  } catch {
    /* best-effort — sign-out should never block on this */
  }
}

/**
 * Open the target a tapped push points at, mirroring the in-app row
 * gesture. Targets are app web paths/URLs, so relative paths resolve
 * against the API host and hand off to the in-app browser (falling back
 * to the OS handler).
 */
function openPushTarget(target: string): void {
  const absolute = /^https?:\/\//i.test(target)
    ? target
    : `${getBaseUrl()}${target.startsWith("/") ? "" : "/"}${target}`;
  WebBrowser.openBrowserAsync(absolute).catch(() => {
    Linking.openURL(absolute).catch(() => {});
  });
}

/**
 * Extract the note id from a note-due reminder payload, or null when the
 * payload isn't a note reminder / carries no usable `note_id`.
 */
export function parseNoteId(
  data: Record<string, unknown> | undefined,
): number | null {
  if (data?.type !== "dialer.note_due") return null;
  const raw = data?.note_id;
  const id =
    typeof raw === "number"
      ? raw
      : typeof raw === "string" && raw.trim() !== ""
        ? Number(raw)
        : NaN;
  return Number.isFinite(id) && id > 0 ? id : null;
}

/**
 * Extract the phone number from a Zio Browser click-to-call push payload,
 * or null when the payload isn't a call request / carries no usable number.
 */
export function parseCallRequestNumber(
  data: Record<string, unknown> | undefined,
): string | null {
  if (data?.type !== "dialer.call_request") return null;
  const raw = data?.number;
  if (typeof raw !== "string") return null;
  const trimmed = raw.trim();
  return trimmed !== "" ? trimmed : null;
}

/**
 * Decide what a tapped push should do, purely from its `data` payload.
 * Kept side-effect free (no markRead / navigation here) so the branch
 * logic that guards deep-linking + mark-read can be unit-tested in
 * isolation — see `scripts/test-push-action.mjs`.
 *
 *   - `markReadId`: the originating in-app row to mark read, or null when
 *     the payload carried no usable `notification_id`.
 *   - `navigation`: note-due reminders deep-link into the Notes tab (the
 *     screen opens the note's editor sheet for own notes, or highlights
 *     shared ones); otherwise open the deep-link `target` when the server
 *     stamped a `url`; otherwise fall back to the dialer home (this
 *     standalone app has no notifications / API-usage / admin screens).
 */
export function decidePushAction(data: Record<string, unknown> | undefined): {
  markReadId: number | null;
  navigation:
    | { kind: "open"; target: string }
    | { kind: "route"; path: string }
    | { kind: "note"; noteId: number }
    | { kind: "call"; number: string; name: string | null };
} {
  const rawId = data?.notification_id;
  const id =
    typeof rawId === "number"
      ? rawId
      : typeof rawId === "string" && rawId.trim() !== ""
        ? Number(rawId)
        : NaN;
  const markReadId = Number.isFinite(id) ? id : null;

  // Note/to-do reminders (local alarms and server pushes alike) carry
  // `type: "dialer.note_due"` + `note_id`. Route them into the in-app Notes
  // tab — never the web fallback — so the tap lands right on the note.
  const noteId = parseNoteId(data);
  if (noteId !== null) {
    return { markReadId, navigation: { kind: "note", noteId } };
  }

  // Click-to-call handoff from the Zio Browser Dialer pane: deep-link the
  // dialer keypad with the number pre-filled (the user confirms the call
  // with the in-app call button — never auto-dialed from a push tap).
  const callNumber = parseCallRequestNumber(data);
  if (callNumber !== null) {
    const name = typeof data?.name === "string" && data.name.trim() !== ""
      ? data.name.trim()
      : null;
    return { markReadId, navigation: { kind: "call", number: callNumber, name } };
  }

  const target = typeof data?.url === "string" ? data.url : null;
  if (target) {
    return { markReadId, navigation: { kind: "open", target } };
  }

  // The standalone dialer has no notifications / API-usage / admin screens
  // (those live in the main Sayzio app), so every other fallback routes to
  // the dialer home.
  return { markReadId, navigation: { kind: "route", path: "/(tabs)/dialer" } };
}

/**
 * Route to the right destination when the user taps a push notification.
 * We deep-link to the exact same target the in-app row uses (carried as
 * `url` in the push data, resolved server-side by the single source of
 * truth) and mark the originating row read in the same gesture. When no
 * target is present we fall back gracefully to the dialer home.
 */
export function addPushResponseListener(): Notifications.EventSubscription {
  return Notifications.addNotificationResponseReceivedListener((response) => {
    const data = response.notification.request.content.data as
      | Record<string, unknown>
      | undefined;

    // "Mark done" quick action on a note reminder: complete the note in
    // place (best-effort) and dismiss the notification — no navigation,
    // the app stays in the background.
    if (response.actionIdentifier === NOTE_MARK_DONE_ACTION) {
      const noteId = parseNoteId(data);
      if (noteId !== null) {
        updateNote(noteId, { done: true })
          .then(() => cancelNoteAlarm(noteId))
          .catch(() => {});
      }
      Notifications.dismissNotificationAsync(
        response.notification.request.identifier,
      ).catch(() => {});
      return;
    }

    const { markReadId, navigation } = decidePushAction(data);

    // Mark just the originating row read (best-effort), so the badge/list
    // stay in sync with the tap.
    if (markReadId !== null) {
      markRead(markReadId).catch(() => {});
    }

    if (navigation.kind === "open") {
      openPushTarget(navigation.target);
      return;
    }
    if (navigation.kind === "call") {
      // Land on the dialer with the requested number pre-filled; the user
      // places the call from the keypad's call button.
      router.push({
        pathname: "/(tabs)/dialer",
        params: {
          prefill: navigation.number,
          ...(navigation.name ? { name: navigation.name } : {}),
          openedAt: String(Date.now()),
        },
      });
      return;
    }
    if (navigation.kind === "note") {
      // Land on the Notes tab; the screen opens the editor sheet for own
      // notes (mark done / snooze / edit in one tap) or highlights shared
      // ones in the list.
      router.push({
        pathname: "/(tabs)/notes",
        params: { noteId: String(navigation.noteId), openedAt: String(Date.now()) },
      });
      return;
    }
    router.push("/(tabs)/dialer");
  });
}
